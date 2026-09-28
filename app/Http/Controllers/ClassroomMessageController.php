<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\ClassroomMessage;
use App\Models\ClassroomMessageRead;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Policies\ClassroomWorkPolicy;
use App\Support\UrlCipher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Classroom message channel.
 *
 * One shared conversation per classroom: the owning teacher and every roster
 * student read and post the same thread. A single controller serves both roles
 * because ClassroomWorkPolicy::canViewClassroom already encodes exactly the two
 * audiences that may take part (owning teacher or roster student; admins via
 * ownsClassroom).
 *
 * The target host has no cron, no queue worker and no long-lived processes, so
 * there is no push channel. The client polls this endpoint while the messages
 * panel is open and the tab is idle; `after` is a cursor so an incremental poll
 * stays cheap.
 */
class ClassroomMessageController extends Controller
{
    /** Rows a single list call may return. */
    private const PAGE_SIZE = 100;

    /** Body preview kept in the notification row. */
    private const PREVIEW = 120;

    public function __construct(private ClassroomWorkPolicy $work) {}

    /**
     * Read the channel.
     *
     * Rows always come back oldest-first, whichever cursor is used, so the
     * client can append a newer batch and prepend an older batch with the same
     * code path and never has to reverse anything.
     *
     *   (none)        the newest page
     *   after=<id>    strictly newer than that id, for the poll
     *   before=<id>   strictly older than that id, for loading history back
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        $user = $request->user();
        abort_unless($this->work->canViewClassroom($user, $classroom), 403);

        $after = $this->parseCursor($request->query('after'), $classroom->id);
        $before = $after === null
            ? $this->parseCursor($request->query('before'), $classroom->id)
            : null;

        $read = $this->readPointer($classroom->id, $user->user_id);
        $unread = $this->unreadCount($classroom->id, $user->user_id, (int) ($read?->lastReadSeq ?? 0));

        // Badge-only read. Lets the tab show an unread count before the thread
        // has ever been opened without transferring a page of messages, so the
        // authorization cost is the same but the message query is skipped.
        if ($request->boolean('countOnly')) {
            return response()->json([
                'ok' => true,
                'unreadCount' => $unread,
                'lastReadAt' => $read?->lastReadAt?->toIso8601String(),
            ]);
        }

        $query = ClassroomMessage::query()
            ->where('classroom_id', $classroom->id)
            ->with(['sender:id,user_id,firstName,lastName,role'])
            ->orderByDesc('seq');

        // seq is a strict total order, so neither window can skip or repeat a
        // row, and there is no tie for the id to break.
        if ($after !== null) {
            $query->where('seq', '>', (int) $after->seq);
        } elseif ($before !== null) {
            $query->where('seq', '<', (int) $before->seq);
        }

        // Fetch one extra row to learn whether a further page exists without a
        // second count query.
        $rows = $query->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $rows->count() > self::PAGE_SIZE;

        $messages = $rows->take(self::PAGE_SIZE)->reverse()->values();

        return response()->json([
            'ok' => true,
            'messages' => $messages->map(fn (ClassroomMessage $m) => $this->serialize($m, $user->user_id))->values(),
            'hasMore' => $hasMore,
            'unreadCount' => $unread,
            'lastReadAt' => $read?->lastReadAt?->toIso8601String(),
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        $user = $request->user();
        abort_unless($this->work->canViewClassroom($user, $classroom), 403);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:'.ClassroomMessage::MAX_BODY],
            'fileUrl' => ['nullable', 'string', 'max:2000'],
            'fileName' => ['nullable', 'string', 'max:255'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $fileUrl = trim((string) ($validated['fileUrl'] ?? ''));
        $fileName = trim((string) ($validated['fileName'] ?? ''));

        // A message must carry something; an empty composer is not a message.
        if ($body === '' && $fileUrl === '') {
            return response()->json(['ok' => false, 'error' => 'Write a message or attach a file first.'], 422);
        }

        // The file must be one this user actually uploaded into a classroom
        // they can reach; otherwise a client could attach any storage URL.
        if ($fileUrl !== '') {
            abort_unless($this->ownsFile($user, $fileUrl), 422, 'That file is not available to attach.');
        }

        $message = ClassroomMessage::create([
            'classroom_id' => $classroom->id,
            'userId' => $user->user_id,
            'body' => $body !== '' ? $body : null,
            'fileUrl' => $fileUrl !== '' ? $fileUrl : null,
            'fileName' => $fileName !== '' ? $fileName : null,
        ]);

        $message->load('sender:id,user_id,firstName,lastName,role');

        AuditLog::record([
            'entity' => 'classroom',
            'recordId' => $classroom->id,
            'action' => 'classroom.message_sent',
            'actorId' => $user->user_id,
            'notes' => 'Message sent in classroom '.$classroom->name,
        ]);

        $this->notifyOthers($classroom, $user, $message);

        // The sender has by definition seen their own message.
        $this->advanceReadPointer($classroom->id, $user->user_id, (int) $message->seq);

        return response()->json([
            'ok' => true,
            'message' => $this->serialize($message, $user->user_id),
            'unreadCount' => 0,
        ], 201);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $classroom = Classroom::query()->findOrFail($id);
        $user = $request->user();
        abort_unless($this->work->canViewClassroom($user, $classroom), 403);

        $lastReadAt = $this->advanceReadPointer($classroom->id, $user->user_id);

        return response()->json([
            'ok' => true,
            'lastReadAt' => $lastReadAt?->toIso8601String(),
            'unreadCount' => 0,
        ]);
    }

    /**
     * Advance the caller's read pointer to the newest message in the classroom
     * and return the new read time. lastReadSeq only ever moves forward, so a
     * slow request cannot un-read messages a newer request already covered.
     */
    private function advanceReadPointer(string $classroomId, string $userId, ?int $seq = null): ?Carbon
    {
        $now = now();

        $read = ClassroomMessageRead::query()->firstOrNew([
            'classroom_id' => $classroomId,
            'userId' => $userId,
        ]);

        $head = $seq ?? (int) ClassroomMessage::query()
            ->where('classroom_id', $classroomId)
            ->max('seq');

        if ($read->exists
            && $read->lastReadAt
            && $read->lastReadAt->greaterThanOrEqualTo($now)
            && (int) $read->lastReadSeq >= $head) {
            return $read->lastReadAt;
        }

        $read->lastReadAt = $now;
        $read->lastReadSeq = $head;
        $read->save();

        return $now;
    }

    private function readPointer(string $classroomId, string $userId): ?ClassroomMessageRead
    {
        return ClassroomMessageRead::query()
            ->where('classroom_id', $classroomId)
            ->where('userId', $userId)
            ->first();
    }

    /**
     * Messages past the caller's read pointer, excluding their own. The
     * pointer is a sequence number, so a message sent in the same second as the
     * previous one still counts. A member with no pointer (0) sees every
     * message posted by somebody else, which is the correct first impression
     * of a channel they have never opened.
     */
    private function unreadCount(string $classroomId, string $userId, int $lastReadSeq): int
    {
        return ClassroomMessage::query()
            ->where('classroom_id', $classroomId)
            ->where('userId', '!=', $userId)
            ->where('seq', '>', $lastReadSeq)
            ->count();
    }

    /**
     * Alert everyone except the sender. The notification is keyed on the
     * classroom, not the message, so a busy channel collapses to a single
     * unread bell row instead of one per message.
     */
    private function notifyOthers(Classroom $classroom, User $sender, ClassroomMessage $message): void
    {
        $settings = SystemSetting::getInstance();
        $notifyStudents = (bool) ($settings->notifyStudents ?? true);
        $notifyTeachers = (bool) ($settings->notifyTeachers ?? true);

        $preview = $message->body !== null
            ? Str::limit($message->body, self::PREVIEW)
            : ($message->fileName !== null ? 'Sent a file' : 'Sent a message');

        $recipients = [];

        if ($notifyTeachers && (string) $classroom->teacherId !== $sender->user_id) {
            $recipients[] = (string) $classroom->teacherId;
        }

        if ($notifyStudents && $sender->isTeacher()) {
            foreach ($classroom->students()->pluck('users.user_id')->all() as $studentUserId) {
                if ((string) $studentUserId !== $sender->user_id) {
                    $recipients[] = (string) $studentUserId;
                }
            }
        }

        foreach (array_unique($recipients) as $recipientUserId) {
            Notification::alert(
                $recipientUserId,
                'New message in '.$classroom->name,
                $sender->firstName.' '.$sender->lastName.': '.$preview,
                Notification::SOURCE_CLASSROOM,
                (string) $classroom->id,
            );
        }
    }

    /**
     * The attachment must have come from POST /api/classroom-files, which only
     * stores under a random basename in the private classroom-files directory.
     * Anything not matching that shape is rejected here.
     *
     * The path is compared rather than the whole URL because url() emits
     * whatever host the request carries, which is not necessarily APP_URL once
     * the portal sits behind a proxy or a different domain. Comparing only the
     * path also avoids a scheme or port mismatch rejecting a legitimate file.
     */
    private function ownsFile(User $user, string $fileUrl): bool
    {
        $prefix = parse_url(url('/api/classroom-files'), PHP_URL_PATH);
        $path = parse_url($fileUrl, PHP_URL_PATH);

        if (! is_string($prefix) || ! is_string($path) || $prefix === '') {
            return false;
        }

        // normalize() keeps a trailing slash, so the boundary is unambiguous.
        if (! str_starts_with($path, rtrim($prefix, '/').'/')) {
            return false;
        }

        $file = rawurldecode(substr($path, strlen(rtrim($prefix, '/')) + 1));
        $base = basename($file);

        // Reject traversal and any nested path: uploads are flat by basename.
        if ($base === '' || $base !== $file || str_contains($file, '/')) {
            return false;
        }

        return Storage::disk('private')->exists('classroom-files/'.$base);
    }

    /**
     * `after` is the message id of the newest row the client already holds.
     * Scoped to the classroom so an id from another thread cannot be used to
     * steer the window (the query is already classroom-scoped, but a cursor
     * from elsewhere would silently return a confusing slice).
     */
    private function parseCursor(mixed $after, string $classroomId): ?ClassroomMessage
    {
        if (! is_string($after) || trim($after) === '') {
            return null;
        }

        return ClassroomMessage::query()
            ->select(['id', 'seq'])
            ->where('classroom_id', $classroomId)
            ->where('id', trim($after))
            ->first();
    }

    /**
     * @param  string|null  $viewerId  the caller's user_id, so the client can
     *                                 align its own messages without having to
     *                                 know who it is from local storage
     */
    private function serialize(ClassroomMessage $message, ?string $viewerId = null): array
    {
        $sender = $message->sender;

        return [
            'id' => $message->id,
            'ref' => UrlCipher::ref($message->id),
            'body' => $message->body,
            'fileUrl' => $message->fileUrl,
            'fileName' => $message->fileName,
            'createdAt' => $message->created_at?->toIso8601String(),
            'mine' => $viewerId !== null && $message->userId === $viewerId,
            'sender' => $sender ? [
                'userId' => $sender->user_id,
                'firstName' => $sender->firstName,
                'lastName' => $sender->lastName,
                'role' => $sender->role,
            ] : null,
        ];
    }
}
