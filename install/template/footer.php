<?php if (!defined("IN_SB")) { echo "You should not be here. Only follow links!"; die(); } ?>
			</div>
		</section>
	</section>

	<footer id="footer">
		<div id="sm">
			Создано <a class="footer_link" href="https://github.com/lapplandbro" target="_blank" rel="noopener">lapplandbro</a>
		</div>
		<ul class="f-menu">
			<li><?php echo SB_VERSION; ?></li>
			<li><a href="https://github.com/lapplandbro" target="_blank" rel="noopener" class="footer_link">Material Admin</a></li>
		</ul>
	</footer>

	<script src="../themes/new_box/vendors/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
	<script src="../themes/new_box/vendors/bower_components/Waves/dist/waves.min.js"></script>
	<script src="../themes/new_box/vendors/bower_components/bootstrap-sweetalert/lib/sweet-alert.min.js"></script>
	<script>
		(function ($) {
			// Installer does not load themes/.../functions.js (Material demo glue).
			var title = <?php echo json_encode(isset($GLOBALS['TitleRewrite']) ? $GLOBALS['TitleRewrite'] : '', JSON_UNESCAPED_UNICODE); ?>;
			if (title) $('#content_title').text(title);
			if ($.fn.popover) $('[data-toggle="popover"]').popover();
			if (window.Waves) {
				Waves.attach('.btn:not(.btn-icon)');
				Waves.init();
			}
		})(jQuery);
	</script>
</body>
</html>
