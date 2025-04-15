<?php
require_once __DIR__ . '/language.php';
$languages = get_available_languages();
$current_language = get_current_language();

// Get current URL and parameters
$currentUrl = $_SERVER['REQUEST_URI'];
// Remove existing lang parameter if present
$currentUrl = preg_replace('/[?&]lang=[^&]+/', '', $currentUrl);
// Remove trailing ? or & if present
$currentUrl = rtrim($currentUrl, '?&');
// Determine separator
$separator = (strpos($currentUrl, '?') === false) ? '?' : '&';
?>

<div class="dropdown">
    <button class="btn btn-light dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-globe"></i>
        <?php foreach ($languages as $lang): ?>
            <?php if ($lang['code'] === $current_language): ?>
                <?php echo htmlspecialchars($lang['name']); ?>
                <?php break; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </button>
    <ul class="dropdown-menu <?php echo get_language_direction() === 'rtl' ? 'dropdown-menu-end' : ''; ?>" aria-labelledby="languageDropdown">
        <?php foreach ($languages as $lang): ?>
            <li>
                <a class="dropdown-item <?php echo $lang['code'] === $current_language ? 'active' : ''; ?>" 
                   href="<?php echo $currentUrl . $separator . 'lang=' . htmlspecialchars($lang['code']); ?>"
                   hreflang="<?php echo htmlspecialchars($lang['code']); ?>"
                   lang="<?php echo htmlspecialchars($lang['code']); ?>">
                    <?php if ($lang['direction'] === 'rtl'): ?>
                        <span dir="rtl"><?php echo htmlspecialchars($lang['name']); ?></span>
                    <?php else: ?>
                        <?php echo htmlspecialchars($lang['name']); ?>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<style>
[dir="rtl"] .dropdown-menu-end {
    --bs-position: start;
}
[dir="rtl"] .dropdown .dropdown-toggle::after {
    margin-right: 0.255em;
    margin-left: 0;
}
</style>
