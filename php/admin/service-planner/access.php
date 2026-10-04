<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';

// Use the current full-access gate. No user records or role definitions change.
function sp_full_access(): bool { return admin_can_manage_users(); }
function sp_can_edit_sermon(): bool { return sp_full_access(); }
function sp_can_edit_worship(): bool { return sp_full_access() || admin_has_role('music_minister'); }
function sp_can_operate_media(): bool { return sp_full_access() || admin_has_role('media', 'sound'); }
function sp_can_manage_services(): bool { return sp_can_edit_worship() || sp_can_operate_media(); }

function sp_can_open_section(string $section): bool
{
    if ($section === 'sermon' || $section === 'announcements') return sp_can_edit_sermon();
    if ($section === 'worship') return sp_can_edit_worship();
    if ($section === 'media' || str_starts_with($section, 'computer')) return sp_can_operate_media();
    return (bool)admin_current_user();
}

function sp_can_save_action(string $action): bool
{
    return match ($action) {
        'sermon', 'announcements' => sp_can_edit_sermon(),
        'worship' => sp_can_edit_worship(),
        'task', 'instructions', 'foh_status' => sp_can_operate_media(),
        'create' => sp_can_manage_services(),
        'archive' => sp_full_access(),
        default => false,
    };
}

function sp_require_access(bool $allowed): void
{
    if (!$allowed) { http_response_code(403); exit('You do not have permission to access this area.'); }
}

function sp_selected_computers($input): array
{
    if (!is_array($input)) return [];
    return array_values(array_intersect(['foh', 'computer1', 'computer2', 'computer3', 'computer4'], array_filter($input,'is_string')));
}
