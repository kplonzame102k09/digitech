@include('partials.profile-page', [
    'role' => 'admin',
    'headerTitle' => 'My Profile',
    'headerSubtitle' => 'Admin Portal',
    'heroClass' => 'student-hero',
    'eyebrowClass' => 'text-purple-200',
    'heroTitle' => 'Your admin profile',
    'heroIntro' => 'Keep your contact information current so the college can reach you when needed.',
    'saveButtonClass' => 'bg-purple-600 hover:bg-purple-700',
    'accentTextClass' => 'text-purple-600 dark:text-purple-300',
    'accentCardIconClass' => 'bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-300',
])