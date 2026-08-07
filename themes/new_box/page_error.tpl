<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="ru">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>SourceBans :: {$title}</title>
        <script type="text/javascript" src="scripts/mootools.js"></script>
        <script type="text/javascript" src="scripts/sourcebans.js"></script>
        <link rel="Shortcut Icon" href="images/favicon.ico">
        
        <link href="themes/new_box/css/rubik.css" rel="stylesheet">
        <link href="themes/new_box/vendors/bower_components/animate.css/animate.min.css" rel="stylesheet">
        <link href="themes/new_box/vendors/bower_components/bootstrap-sweetalert/lib/sweet-alert.css" rel="stylesheet">
        <link href="themes/new_box/vendors/bower_components/material-design-iconic-font/dist/css/material-design-iconic-font.min.css" rel="stylesheet">
        <link href="themes/new_box/vendors/bower_components/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.min.css" rel="stylesheet">
        <link href="themes/new_box/css/app.min.1.css" rel="stylesheet">
        <link href="themes/new_box/css/app.min.2.css" rel="stylesheet">
        <link href="themes/new_box/css/css_sup.css" rel="stylesheet">
        <link href="themes/new_box/css/dark-blue-theme.css" rel="stylesheet">
    </head>
    <body class="toggled sw-toggled">
        <header id="header" class="clearfix" data-current-skin="blue">
            <ul class="header-inner">
                <li id="menu-trigger" data-trigger="#sidebar">
                    <div class="line-wrap">
                        <div class="line top"></div>
                        <div class="line center"></div>
                        <div class="line bottom"></div>
                    </div>
                </li>
                <li class="logo hidden-xs">
                    <a href="index.php">
                        SourceBans :: MATERIALS
                    </a>
                </li>
            </ul>
        </header>
        
        <section id="main" data-layout="layout-1">
            <aside id="sidebar" class="sidebar c-overflow">
                <div class="profile-menu">
                    <a href="#">
                        <div class="profile-pic">
                            <img src="themes/new_box/img/profile-pics/1.jpg" />
                        </div>

                        <div class="profile-info">
                            Ошибка
                        </div>
                    </a>
                </div>

                <ul class="main-menu">
                    <li class='nonactive'><a href="http://www.sourcebans.net" target="_blank"><i class='zmdi zmdi-globe'></i> SourceBans</a></li>
                    <li class='nonactive'><a href="http://www.sourcemod.net" target="_blank"><i class='zmdi zmdi-flower-alt'></i> SourceMod</a></li>
                </ul>
            </aside>
            <section id="content">
                <div class="container">
                    <!-- <div class="block-header">
                        <h2 id="content_title">
                            {$title}
                        </h2>
                    </div> -->
                <div id="msg-red-debug" style="display:none;" >
                    <i><img class="sb-ico" src="images/icons/warning.svg" width="22" height="22" alt="Warning" /></i>
                    <b>Debug</b>
                    <br />
                    <div id="debug-text"></div></i>
                </div>
                <div class="card login-content go-social">
                    <div class="card-header">
                        <h2>
                            {$title}
                        </h2>
                    </div>
                    <div class="card-body card-padding">
                        <p>{$message}</p>
                        <pre>{$pfunction}</pre>
                    </div>
                </div>
            </section>
        </section>
        <footer id="footer">
            <div id="sm">
                Создано <a class="footer_link" href="https://github.com/lapplandbro" target="_blank" rel="noopener">lapplandbro</a>
                <span class="footer-stack">
                    · стек:
                    <a class="footer_link" href="https://www.sourcemod.net/" target="_blank" rel="noopener">SourceMod</a>,
                    <a class="footer_link" href="https://jquery.com/" target="_blank" rel="noopener">jQuery</a>,
                    <a class="footer_link" href="https://github.com/Xajax/Xajax" target="_blank" rel="noopener">xAjax</a>,
                    <a class="footer_link" href="https://www.php.net/" target="_blank" rel="noopener">PHP</a>,
                    <a class="footer_link" href="https://getbootstrap.com/docs/3.4/" target="_blank" rel="noopener">Bootstrap&nbsp;3</a>
                </span>
            </div>
        </footer>

        <!--[if lt IE 7]>
        <script defer type="text/javascript" src="./scripts/pngfix.js"></script>
        <![endif]-->

        <script src="themes/new_box/vendors/bower_components/jquery/dist/jquery.min.js"></script>
        <script src="themes/new_box/vendors/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
        <script src="themes/new_box/vendors/bower_components/Waves/dist/waves.min.js"></script>
        <script src="themes/new_box/vendors/bower_components/bootstrap-sweetalert/lib/sweet-alert.min.js"></script>
        <script src="themes/new_box/vendors/bower_components/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.concat.min.js"></script>
        <script src="themes/new_box/js/functions.js"></script>
        <script>
            $.noConflict();
        </script>
    </body>
</html>
