<?php
require_once __DIR__.'/lib/crypto.php';
ob_start();
logged_in();
$key = "3c5340eca1e0a1ac201e4ae648ba11f2";
$userId = Crypto::decrypt($_COOKIE['oc_user_logged_in']);
$userStatusCookie = Crypto::decrypt($_COOKIE['oc_user_status']);
$userDisplayName = $_COOKIE['gsi_oc_user_displayName'];
$userStatus = explode("#", $userStatusCookie);
if ($userId == $userStatus[0] && $userStatus[1] == "admin" || $userId == "admin" || $userId == "akamis" || $userId == "acugur") {
    $adminStatus = "true";
} else {
    $adminStatus = "false";
}
header('Content-Type: text/html; charset=utf-8');

?><!DOCTYPE html>
<html lang="en" class="full">
<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>GSI Mecelle Knowledge Management System</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css?v=<?php echo $version; ?>" rel="stylesheet">
    <link href="/css/bootstrap-theme.css?v=<?php echo $version; ?>" rel="stylesheet">
    <link href="/css/gsi.css?v=<?php echo $version; ?>" rel="stylesheet">
    <link href="/js/select/dist/css/select2.min.css?v=<?php echo $version; ?>" rel="stylesheet">
    <link href="/css/bootstrap-datepicker3.min.css?v=<?php echo $version; ?>" rel="stylesheet">
    <link href="/css/font-awesome.min.css?v=<?php echo $version; ?>" rel="stylesheet">

    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js?v=<?php echo $version;?>"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js?v=<?php echo $version;?>"></script>
    <![endif]-->
    <script type="text/javascript" src="/js/jquery-3.2.1.min.js"></script>
    <script src="/js/typeahead.bundle.js"></script>
    <link rel="shortcut icon" type="image/png" href="/images/favicon.png"/>

    <link rel="stylesheet" href="/list/css/style.css?v=<?php echo $version; ?>">

    <link href="/rapor/dist/css/sb-admin-2.css?v=<?php echo $version; ?>" rel="stylesheet">
    <link href="/rapor/jquery-ui-1.12.0.custom/jquery-ui.css?v=<?php echo $version; ?>" rel="stylesheet">

    <script src="/js/function.js?v=<?php echo $version; ?>"></script>
    <script src="/js/socket.js?v=<?php echo $version; ?>"></script>
    <script type="text/javascript" src="/web-push/src/app.js?v=<?php echo $version; ?>"></script>
</head>
<style media="screen">
    #header-top {
        font-weight: 400;
        font-size: .8em;
        line-height: 1.6em;
        font-family: 'Open Sans', Frutiger, Calibri, 'Myriad Pro', Myriad, sans-serif;
        color: #000;
    }

</style>

<div id="header-top">
    <div id="header">
        <a href="/index.php" id="owncloud" tabindex="1">
            <div class="logo-icon">
            </div>
        </a>

        <a href="#" class="header-appname-container menutoggle" tabindex="2">
            <h1 class="header-appname">

            </h1>
            <div class="icon-caret"></div>
        </a>

        <div id="logo-claim" style="display:none;"></div>
        <header id="settings_HEADER_23">
            <div id="settings_DIV_24">
                <div id="settings_DIV_1">
                    <div id="settings_DIV_2">
                        <div id="settings_DIV_3">
                        </div>
                        <span id="settings_SPAN_4"><?php echo $userDisplayName; ?></span>
                    </div>
                    <div id="settings_DIV_6">
                        <ul id="settings_UL_7">
                            <?php if ($adminStatus == "true"): ?>
                                <li id="settings_LI_11">
                                    <a href="https://docs.gsimecelle.com/index.php/settings/users"
                                       id="settings_A_12"><img alt=""
                                                               src="https://docs.gsimecelle.com/settings/img/users.svg?v=19492a51818390a3155229b391c1430e"
                                                               id="settings_IMG_13"/> Kullanıcılar</a>
                                </li>
                                <li id="settings_LI_14">
                                    <a href="https://docs.gsimecelle.com/index.php/settings/admin"
                                       id="settings_A_15"><img alt=""
                                                               src="https://docs.gsimecelle.com/settings/img/admin.svg?v=19492a51818390a3155229b391c1430e"
                                                               id="settings_IMG_16"/> Yönetici</a>
                                </li>
                            <?php endif; ?>
                            <li id="settings_LI_20">
                                <a id="settings_A_21" href="https://docs.gsimecelle.com/index.php/logout"><img alt=""
                                                                                                         src="https://docs.gsimecelle.com/core/img/actions/logout.svg?v=19492a51818390a3155229b391c1430e"
                                                                                                         id="settings_IMG_22"/>
                                    Çıkış yap</a>
                            </li>

                        </ul>
                    </div>
                </div>
            </div>
        </header>
    </div>
    <nav role="navigation">
        <div id="navigation" class="menu" style="display: none;">
            <div id="apps">
                <ul>
                    <li data-id="files_index">
                        <a href="#" tabindex="3" class="active">
                            <i id="backButton" class="fa fa-arrow-left fa-2x" aria-hidden="true"
                               onclick="history.back(-1)"></i>

                            <div class="icon-loading-dark" style="display:none;"></div>
                            <span>
    								Geri							</span>
                        </a>
                    </li>
                    <li data-id="files_index">
                        <a href="https://docs.gsimecelle.com/index.php/apps/files/" tabindex="3" class="active">
                            <svg width="20" height="20" viewBox="0 0 32 32">
                                <defs>
                                    <filter id="invert">
                                        <feColorMatrix in="SourceGraphic" type="matrix"
                                                       values="-1 0 0 0 1 0 -1 0 0 1 0 0 -1 0 1 0 0 0 1 0"></feColorMatrix>
                                    </filter>
                                </defs>
                                <image x="0" y="0" width="32" height="32" preserveAspectRatio="xMinYMin meet"
                                       xmlns:xlink="https://www.w3.org/1999/xlink"
                                       xlink:href="https://docs.gsimecelle.com/core/img/places/files.svg?v=19492a51818390a3155229b391c1430e"
                                       class="app-icon"></image>
                            </svg>
                            <div class="icon-loading-dark" style="display:none;"></div>
                            <span>
    								Dosyalar							</span>
                        </a>
                    </li>
                    <li data-id="activity">
                        <a href="https://docs.gsimecelle.com/index.php/apps/activity/" tabindex="3">
                            <svg width="20" height="20" viewBox="0 0 32 32">
                                <defs>
                                    <filter id="invert">
                                        <feColorMatrix in="SourceGraphic" type="matrix"
                                                       values="-1 0 0 0 1 0 -1 0 0 1 0 0 -1 0 1 0 0 0 1 0"></feColorMatrix>
                                    </filter>
                                </defs>
                                <image x="0" y="0" width="32" height="32" preserveAspectRatio="xMinYMin meet"
                                       xmlns:xlink="https://www.w3.org/1999/xlink"
                                       xlink:href="https://docs.gsimecelle.com/apps/activity/img/activity.svg?v=19492a51818390a3155229b391c1430e"
                                       class="app-icon"></image>
                            </svg>
                            <div class="icon-loading-dark" style="display:none;"></div>
                            <span>
    								Etkinlik							</span>
                        </a>
                    </li>
                    <?php if ($adminStatus == "true"): ?>

                        <li data-id="logreader">
                            <a href="https://docs.gsimecelle.com/index.php/apps/logreader/" tabindex="3">
                                <svg width="20" height="20" viewBox="0 0 32 32">
                                    <defs>
                                        <filter id="invert">
                                            <feColorMatrix in="SourceGraphic" type="matrix"
                                                           values="-1 0 0 0 1 0 -1 0 0 1 0 0 -1 0 1 0 0 0 1 0"></feColorMatrix>
                                        </filter>
                                    </defs>
                                    <image x="0" y="0" width="32" height="32" preserveAspectRatio="xMinYMin meet"
                                           xmlns:xlink="https://www.w3.org/1999/xlink"
                                           xlink:href="https://docs.gsimecelle.com/apps/logreader/img/app.svg?v=19492a51818390a3155229b391c1430e"
                                           class="app-icon"></image>
                                </svg>
                                <div class="icon-loading-dark" style="display:none;"></div>
                                <span>
    								Log reader							</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li data-id="activity">
                        <a href="https://docs.gsimecelle.com/index.php/apps/flowupload/" tabindex="3">
                            <svg width="20" height="20" viewBox="0 0 32 32">
                                <defs>
                                    <filter id="invert">
                                        <feColorMatrix in="SourceGraphic" type="matrix"
                                                       values="-1 0 0 0 1 0 -1 0 0 1 0 0 -1 0 1 0 0 0 1 0"></feColorMatrix>
                                    </filter>
                                </defs>
                                <image x="0" y="0" width="32" height="32" preserveAspectRatio="xMinYMin meet"
                                       xmlns:xlink="https://www.w3.org/1999/xlink"
                                       xlink:href="https://docs.gsimecelle.com//apps/flowupload/img/flowupload.svg?v=8ab59bfe1e38a94fdb7a6743b7dfb548"
                                       class="app-icon"></image>
                            </svg>
                            <div class="icon-loading-dark" style="display:none;"></div>
                            <span>
                  Dosya Yükle							</span>
                        </a>
                    </li>


                    <li id="doc_li">
                        <a href="#" onclick="document.location='/'" title="Mecelle Search"
                           class="btn btn-default header-icon"><i style="font-size:20px" class="fa fa-search"></i></a>

                    </li>

                    <li id="doc_li">
                        <a href="#" onclick="document.location='/social?action=documentLists'"
                           title="Çalışma Dosyaları"
                           class="btn btn-default header-icon"><i style="font-size:20px" class="fa fa-briefcase"></i></a>

                    </li>
                    <li id="history_li">
                        <a href="#" id="historyLink" title="Arama Geçmişi" class="btn btn-default header-icon"><i
                                    style="font-size:20px" class="fa fa-history"></i></a>
                        <div id="historyContainer">
                            <!--                            <div id="historyTitle"></div>-->
                            <div id="historysBody" class="historys">
                            </div>
                            <div style="text-align: center;"><a href="#"
                                                                onclick="document.location='/social?action=searchHistory'"
                                                                style="width: auto !important;"><strong>Hepsini
                                        Gör</strong></a></div>
                        </div>

                    </li>

                    <li id="doc_li">
                        <a href="#" id="docLink" title="Döküman Görüntüleme Geçmişi"
                           class="btn btn-default header-icon"><i style="font-size:20px" class="fa fa-book"></i></a>
                        <div id="docContainer">
                            <!--                            <div id="docTitle"></div>-->
                            <div id="docsBody" class="historys">
                            </div>
                            <div style="text-align: center;"><a href="#"
                                                                onclick="document.location='/social?action=searchDocs'"
                                                                style="width: auto !important;"><strong>Hepsini
                                        Gör</strong></a></div>
                        </div>

                    </li>
                    <!--                    <li id="notification_li">-->
                    <!--                        <a href="#" id="notLink" title="Mecelle Bilgilendirme"-->
                    <!--                           class="btn btn-default header-icon"><i id="bell" style="font-size:20px" class="fa fa-bell"></i></a>-->
                    <!--                    </li>-->
                    <li id="notification_li">
                        <a href="/social?action=settings" id="notLink" title="Mecelle Ayarlar"
                           class="btn btn-default header-icon"><i id="bell" style="font-size:20px"
                                                                  class="fa fa-gear"></i></a>
                    </li>
                    <li>
                        <?php
                            $userName = current_user_name();
                            $users = array("HYENGIN", "FGAKANSEL", "CBAL", "AYBOLUCEK");
                            $cookie_name = "oc_user_logged_in_groups";
                            if (isset($_COOKIE[$cookie_name])) {

                                $restric = getUserPermission($cookie_name);

                            } else {

                                $restric = true; //can_view_library(current_user_name());

                            }
                        ?>
                        <a href="/rapor?isReport=<?php echo $restric ? 0 : 1 ?>" id="docLink"
                           title="Rapor"
                           class="btn btn-default header-icon"><i style="font-size:20px" class="fa fa-pie-chart"></i></a>

                    </li>
                </ul>
            </div>
        </div>
    </nav>
</div>

<input type="hidden" id="userName" value="<?php echo current_user_name() ?>"/>
