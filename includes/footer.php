<?php
// includes/footer.php
// Standardized Footer section to be used across the application.
//
// Expected variables from the parent file:
// $extra_js - String for any additional <script> inclusions
?>
    <!-- Core Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Global Guest Interaction Prompt
        function requireLoginPrompt(actionName) {
            Swal.fire({
                title: 'กรุณาเข้าสู่ระบบ',
                html: `คุณต้องเป็นสมาชิกของ UDRU Wisdom ก่อนจึงจะสามารถ<b>${actionName}</b>ได้ครับ`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'เข้าสู่ระบบ / สมัครสมาชิก',
                cancelButtonText: 'ไว้ทีหลัง',
                confirmButtonColor: '#2d8a7d',
                cancelButtonColor: '#6e7881',
                padding: '2rem',
                customClass: {
                    title: 'swal2-title-thai',
                    content: 'swal2-content-thai',
                    confirmButton: 'swal2-confirm-thai',
                    cancelButton: 'swal2-cancel-thai'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'login.php';
                }
            });
            return false;
        }
    </script>
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
