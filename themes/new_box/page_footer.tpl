</div>
            </section>
        </section>
        
        <footer id="footer">
			<div id="sm">
				Создано <a class="footer_link" href="https://github.com/lapplandbro" target="_blank" rel="noopener">lapplandbro</a>
			</div>

            <ul class="f-menu f-menu-seo">
                <li><a href="index.php">Главная</a></li>
                <li><a href="index.php?p=servers">Серверы</a></li>
                <li><a href="index.php?p=banlist">Банлист</a></li>
                <li><a href="index.php?p=commslist">Муты</a></li>
                <li><a href="index.php?p=adminlist">Админы</a></li>
            </ul>
            
            <ul class="f-menu">
                <li>Версия <b>{$SB_VERSION}</b></li>
                <li><a href="https://github.com/lapplandbro" target="_blank" rel="noopener" class="footer_link">Material Admin</a></li>
            </ul>
            {if $show_gendata}
            <ul class="f-menu">
                <li>Сгенерировано за {$gendata_time} секунд</li>
                <li>Выполнено {$gendata_queries} запросов к БД</li>
            </ul>
            {/if}
        </footer>

        <!-- Page Loader -->
        {if $splash_screen}
        <div class="page-loader" id="page-loader">
            <div class="preloader pls-blue">
                <svg class="pl-circular" viewBox="25 25 50 50">
                    <circle class="plc-path" cx="50" cy="50" r="20" />
                </svg>
                <p>Загрузка</p>
            </div>
        </div>
        {literal}
        <script>
        (function(){
            var loader = document.getElementById('page-loader');
            if(!loader){return;}
            var done = false;
            function hide(){
                if(done){return;}
                done = true;
                loader.style.transition = 'opacity .25s ease';
                loader.style.opacity = '0';
                setTimeout(function(){
                    if(loader && loader.parentNode){ loader.parentNode.removeChild(loader); }
                }, 300);
            }
            // Гасим только по полной загрузке всех ресурсов (window.load), а не по DOM —
            // иначе спиннер пропадал, пока страница ещё догружалась.
            if(document.readyState === 'complete'){
                setTimeout(hide, 500);
            } else {
                window.addEventListener('load', function(){ setTimeout(hide, 500); });
            }
            // Аварийный предохранитель на случай зависшего ресурса.
            setTimeout(hide, 12000);
        })();
        </script>
        {/literal}
        {/if}
        
		<!-- Javascript Libraries -->
        <script src="themes/new_box/vendors/bower_components/jquery/dist/jquery.min.js" ></script>
        <script src="themes/new_box/vendors/bower_components/bootstrap/dist/js/bootstrap.min.js" ></script>
        
        <script src="themes/new_box/vendors/bower_components/flot/jquery.flot.js" ></script>
        <script src="themes/new_box/vendors/bower_components/flot/jquery.flot.resize.js" ></script>
        <script src="themes/new_box/vendors/bower_components/flot.curvedlines/curvedLines.js" ></script>
        <script src="themes/new_box/vendors/sparklines/jquery.sparkline.min.js" ></script>
        <script src="themes/new_box/vendors/bower_components/jquery.easy-pie-chart/dist/jquery.easypiechart.min.js" ></script>
        
        <script src="themes/new_box/vendors/bower_components/moment/min/moment.min.js" ></script>
        <script src="themes/new_box/vendors/bower_components/fullcalendar/dist/fullcalendar.min.js" ></script>
        <script src="themes/new_box/vendors/bower_components/simpleWeather/jquery.simpleWeather.min.js" ></script>
        <script src="themes/new_box/vendors/bower_components/Waves/dist/waves.min.js" ></script>
        <script src="themes/new_box/vendors/bootstrap-growl/bootstrap-growl.min.js" ></script>
        <script src="themes/new_box/vendors/bower_components/bootstrap-sweetalert/lib/sweet-alert.min.js" ></script>
        <script src="themes/new_box/vendors/bower_components/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.concat.min.js" ></script>
        
        <!-- Placeholder for IE9 -->
        <!--[if IE 9 ]>
            <script src="themes/new_box/vendors/bower_components/jquery-placeholder/jquery.placeholder.min.js"></script>
        <![endif]-->
        
        <script src="themes/new_box/js/flot-charts/curved-line-chart.js" ></script>
        <script src="themes/new_box/js/flot-charts/line-chart.js" ></script>
        <script src="themes/new_box/js/charts.js" ></script>
       
		
        <script src="themes/new_box/js/functions.js?v={$asset_ver}" ></script>
        <script src="themes/new_box/js/demo.js?v={$asset_ver}" ></script>
		
		<script src="themes/new_box/vendors/summernote/dist/summernote-updated.min.js" ></script>
		
        <script src="themes/new_box/vendors/bower_components/bootstrap-select/dist/js/bootstrap-select.js" ></script>
        <script src="themes/new_box/vendors/bower_components/chosen/chosen.jquery.min.js" ></script>
		
        <script src="themes/new_box/vendors/input-mask/input-mask.min.js"></script>
        
        <script src="themes/new_box/vendors/fileinput/fileinput.min.js"></script>
		
		<script>
		  $.noConflict();
		</script>
        

	{*/body*}
{*/html*}
