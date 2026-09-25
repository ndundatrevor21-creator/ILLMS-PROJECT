<?php
/** Shared closing markup and optional page-specific JavaScript loader. */
if (!isset($baseUrl)) {
    $baseUrl = dirname($_SERVER['PHP_SELF']);
    if ($baseUrl === '/') {
        $baseUrl = '';
    }
}
?>
</main>
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Lending Management System. Developed and Powered by Trevor.</p>
        </div>
    </footer>
    <script src="<?php echo $baseUrl; ?>/assets/js/main.js"></script>
    <?php 
    // Include page-specific JS if it exists
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    $js_file = "/assets/js/{$current_page}.js";
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $baseUrl . $js_file)) {
        echo "<script src=\"{$baseUrl}{$js_file}\"></script>";
    }
    ?>
</body>
</html>