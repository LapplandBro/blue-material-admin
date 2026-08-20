<?php if (!defined("IN_SB")) { echo "You should not be here. Only follow links!"; die(); } ?>
			</div>
		</section>
	</div>

	<footer id="footer" class="install-foot">
		<div>Создано <a href="https://github.com/lapplandbro" target="_blank" rel="noopener">lapplandbro</a></div>
		<div><?php echo SB_VERSION; ?></div>
	</footer>

	<script src="../themes/blue_v2/vendor/sweetalert/sweet-alert.min.js"></script>
	<script>
		(function () {
			var title = <?php echo json_encode(isset($GLOBALS['TitleRewrite']) ? $GLOBALS['TitleRewrite'] : '', JSON_UNESCAPED_UNICODE); ?>;
			var el = document.getElementById('content_title');
			if (title && el) el.textContent = title;
		})();
	</script>
</body>
</html>
