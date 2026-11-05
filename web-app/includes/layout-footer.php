    </main>
  </div>
</div>

<?php 
// Include Quick Note modal for Super Admin & Authorized Staff
$footer_user = get_logged_user();
$show_quick_note_modal = false;
if ($footer_user) {
    if ($footer_user['role'] === 'super_admin') {
        $show_quick_note_modal = true;
    } elseif ($footer_user['role'] === 'staff') {
        $footer_staff_perms = get_staff_permissions($footer_user['id']);
        if (in_array('quick_note', $footer_staff_perms, true) || in_array('note', $footer_staff_perms, true)) {
            $show_quick_note_modal = true;
        }
    }
}
if ($show_quick_note_modal) {
    require_once __DIR__ . '/quick-note-modal.php'; 
}
?>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script src="<?php echo HRMS_URL; ?>/assets/js/hrms.js?v=<?php echo time(); ?>"></script>
</body>
</html>
