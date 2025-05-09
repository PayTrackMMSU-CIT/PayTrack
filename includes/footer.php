    </div>
    
    <footer class="bg-blue-900 text-white py-6">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h2 class="text-xl font-bold mb-2"><?php echo APP_NAME; ?></h2>
                    <p class="text-sm"><?php echo APP_DESCRIPTION; ?></p>
                </div>
                <div class="flex flex-col text-center md:text-right">
                    <p class="text-sm mb-1">Mariano Marcos State University</p>
                    <p class="text-sm mb-1">College of Industrial Technology</p>
                    <p class="text-sm">&copy; <?php echo date('Y'); ?> All Rights Reserved</p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    
    <?php if (isset($page_specific_js)): ?>
    <!-- Page Specific JS -->
    <?php echo $page_specific_js; ?>
    <?php endif; ?>
</body>
</html>
