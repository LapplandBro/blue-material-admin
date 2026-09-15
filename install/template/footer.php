<?php if (!defined("IN_SB")) { echo "You should not be here. Only follow links!"; die(); } ?>
			</div>
		</section>
	</div>

	<footer id="footer" class="install-foot">
		<div>GNU GPLv3 · форк <a href="https://github.com/sbpp/sourcebans-pp" target="_blank" rel="noopener">SourceBans++</a></div>
		<div>Автор: <a href="https://github.com/LapplandBro/blue-material-admin" target="_blank" rel="noopener">LapplandBro</a> · идея оформления: <a href="https://hlmod.net/threads/alpha-material-admin-refork-na-osnove-sb-1-5-4-7-bootstrap-3.36382/" target="_blank" rel="noopener">HLMod</a> / <a href="https://github.com/CrazyHackGUT" target="_blank" rel="noopener">Kruzya</a></div>
		<div>Самостоятельный форк — отдельно от остальных сборок · <?php echo SB_VERSION; ?></div>
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
