<?php
// File: includes/footer.php - Common Footer
?>
            </div> <!-- End Page Content Inner -->
        </div> <!-- End Main Content Wrapper -->
    </div> <!-- End Wrapper -->
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        window.BASE_URL = <?= json_encode(BASE_URL) ?>;
    </script>
    <script src="<?= BASE_URL ?>assets/js/shared.js"></script>
    <script src="<?= BASE_URL ?>assets/js/sidebar.js"></script>
    <script src="<?= BASE_URL ?>assets/js/animations.js"></script>
    
    <!-- AI Chatbot Widget -->
    <link rel="stylesheet" href="<?= BASE_URL ?>modules/chatbot/chatbot.css">
    <div id="chatbot-bubble" title="Click to chat with AI">💬</div>
    <div id="chatbot-window">
        <div id="chatbot-header">
            <span>🎓 Quaid College Assistant</span>
            <button id="chatbot-close">✕</button>
        </div>
        <div id="chatbot-messages"></div>
        <div id="chatbot-input-area">
            <input type="text" id="chatbot-input" placeholder="Ask anything about admissions, fees...">
            <button id="chatbot-send"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
    <script src="<?= BASE_URL ?>modules/chatbot/chatbot.js"></script>
    <div class="toast-container-modern"></div>

    <script>
        $(document).ready(function() {
            if (window.lucide) {
                lucide.createIcons();
            }
            // Sidebar Submenu State Persistence
            // Restore state on page load
            var activeSubmenu = localStorage.getItem('activeSidebarSubmenu');
            if (activeSubmenu) {
                var $submenu = $('#' + activeSubmenu);
                if ($submenu.length) {
                    $submenu.addClass('show');
                    $submenu.prev('a.dropdown-toggle').attr('aria-expanded', 'true');
                }
            }

            // Save state when a submenu is opened
            $('.collapse').on('shown.bs.collapse', function () {
                if ($(this).parent().parent().hasClass('components')) {
                    localStorage.setItem('activeSidebarSubmenu', $(this).attr('id'));
                }
            });

            // Remove state when a submenu is closed
            $('.collapse').on('hidden.bs.collapse', function () {
                if ($(this).parent().parent().hasClass('components') && localStorage.getItem('activeSidebarSubmenu') === $(this).attr('id')) {
                    localStorage.removeItem('activeSidebarSubmenu');
                }
            });

            // Initialize DataTables
            $('.datatable').DataTable({
                pageLength: 25,
                responsive: true,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries"
                }
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>
