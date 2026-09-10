@include('partials.profile-page', [
    'role' => 'guest',
    'headerTitle' => 'My Profile',
    'headerSubtitle' => 'Guest Portal',
    'heroClass' => 'student-hero',
    'eyebrowClass' => 'text-emerald-600',
    'heroTitle' => 'Your guest profile',
    'heroIntro' => 'Keep your contact information current for college communication and document requests.',
    'saveButtonClass' => 'bg-emerald-600 hover:bg-emerald-700',
    'accentTextClass' => 'text-emerald-600 dark:text-emerald-300',
    'accentCardIconClass' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300',
])