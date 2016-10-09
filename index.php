<?php
// Includes
require_once('config.php');

use Comics\Functions;
use Comics\Storage\DB;

// Establish DB Connection
$DB = DB::setup(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Set Variables
$max_entries = 1;

// Get Comic Links
$comics = $DB->execute("SELECT * FROM bw_comics");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Badwolf Comic Feed</title>

    <!-- Bootstrap -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="bootstrap/css/bootstrap-theme.css" rel="stylesheet">
    <link href="js/jquery-ui/jquery-ui.css" rel="stylesheet">
    <link href="js/jquery-ui/jquery-ui.theme.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>

    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="bootstrap/js/bootstrap.min.js"></script> <!-- Bootstrap -->
    <script defer src="js/jquery-ui/jquery-ui.js"></script> <!-- jqueryUI -->
    <script defer src="js/common.js"></script> <!-- Common JS Functions -->

    <script type="text/javascript">
        $(document).ready(function () {
        });

        $(function () {
            dialog = $("#addRSS-form").dialog({
                autoOpen: false,
                height: 250,
                width: 400,
                modal: true,
                buttons: {
                    "Add RSS Link": function () {
                        var name = $('#rss_name').val();
                        var link = $('#rss_link').val();

                        $.ajax({
                            type: "POST", data: {name: name, link: link}, url: 'ajax.php?addRSSLink', dataType: 'json',
                            success: function (json) {
                                $('#addRSS-form').html('RSS Link for (' + name + ') was added to the database.');
                                dialog.dialog("option", "buttons",
                                    [{
                                        text: "Close",
                                        click: function () {
                                            location.reload();
                                        }
                                    }]
                                );
                            }
                        });
                    },
                    Cancel: function () {
                        dialog.dialog("close");
                    }
                },
                close: function () {
                    $('#rssForm')[0].reset();
                }
            });

            form = dialog.find("form").on("submit", function (event) {
                event.preventDefault();
                addRSSLink();
            });

            $("#addRSS").button().on("click", function () {
                $('#rssForm')[0].reset();
                dialog.dialog("open");
            });
        });
    </script>
</head>
<body>
<a name="top">
    <nav class="navbar navbar-inverse navbar-static-top">
        <div class="container-fluid">
            <div class="navbar-header">
                <a class="navbar-brand" href="#top">Comics</a>
            </div>
            <div class="navbar-collapse collapse">
                <ul class="nav navbar-nav navbar-left">
                    <?php foreach ($comics as $comic) { ?>
                        <li><a href="#<?= $comic['id'] ?>"><?= $comic['name'] ?></a></li> <?php } ?>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li style="margin-top:7px; margin-right:5px;">
                        <button type="button" class="btn btn-primary navbar-right" id="addRSS">Add New Link</button>
                    </li>
                </ul>
            </div><!--/.nav-collapse -->
        </div>
    </nav>

    <div class="container theme-showcase" role="main">
        <?php
        // Load Feeds
        foreach ($comics as $comic) {
            $comic_data = Functions::getComic($comic['id'], $comic['link'], $max_entries); ?>
            <a name="<?= $comic['id'] ?>"></a>
            <div class="row" id="comic_div_<?= $comic['id'] ?>">
                <?php foreach ($comic_data as $data) { ?>
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="panel-title" id="comic_title"><?= $comic['name'] ?>
                                    - <?= $data['title'] ?></h3>
                            </div>
                            <div class="panel-body" id="comic_body">
                                <?= $data['description'] ?>
                            </div>
                        </div>
                    </div>
                    <br/>
                <?php } ?>
            </div>
        <?php } ?>
    </div> <!-- /container -->

    <!--- Add RSS Dialog -->
    <div id="addRSS-form" title="Add New RSS Link">
        <p class="validateTips">All form fields are required.</p>

        <form id="rssForm">
            <fieldset>
                <table cellspacing="2" cellpadding="2">
                    <tr>
                        <td><label for="name">RSS Name: </label></td>
                        <td style="padding-left: 3px;"><input type="text" name="rss_name" id="rss_name" value=""
                                                              class="text ui-widget-content ui-corner-all"></td>
                    </tr>
                    <tr>
                        <td><label for="email">RSS Link: </label></td>
                        <td style="padding-left: 3px;"><input type="text" name="rss_link" id="rss_link" value=""
                                                              class="text ui-widget-content ui-corner-all"></td>
                    </tr>
                </table>

                <!-- Allow form submission with keyboard without duplicating the dialog button -->
                <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
            </fieldset>
        </form>
    </div>
</body>
</html>