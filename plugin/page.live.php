<?php
/**
 * cPanel4J
 * page.live.php
 * Author: Vivek Soni (contact@viveksoni.net)
 * Instructions & More Info: cpanel4j.viveksoni.net
 * Released under the GNU General Public License
 */
//Page.php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
require_once "/usr/local/cpanel/php/cpanel.php";
require_once "/cPanel4jCore/DBWrapper.php";
require_once "/cPanel4jCore/Tomcat.php";
require_once('/cPanel4jCore/libs/log4php/Logger.php');
\Logger::configure('/cPanel4jCore/log4phpConfig.xml');
$logger = \Logger::getLogger("main");
$who = 'page.live.php|';
$logger->debug($who . 'INSIDE');
$cpanel = new CPANEL();
//$cpanel->set_debug(1);
$domainListApiCall = $cpanel->api2('DomainLookup', 'getdocroot', array());
$domainList = $domainListApiCall['cpanelresult']['data'];
$domainList = $domainList['0'];
$docRoot = $domainList['docroot'];
$roots = explode("/", $docRoot);
$userName = $roots['2'];
$action = $_GET['action'];
if ($action == "list") {
    $logger->debug($who . 'List: INSIDE');
    echo $cpanel->header('View Tomcat Instances- cPanel4J');
    $DBWrapper = new \cPanel4jCore\DBWrapper();
    $count = 1;
    $pending_flag = 0;

    if (isset($_GET['error'])) {
        echo '<div class="alert alert-info" role="alert">' . $_GET['error'] . '</div>';
    }
    $instanceResult = $DBWrapper->getTomcatInstancesByUser($userName);
    echo "<table class='table'><tr><th>#</th><th>Instance Info</th><th>Tomcat Version</th><th>Status</th><th>Create Date</th><th>Ports</th><th>Action</th></tr>";
    if (mysqli_num_rows($instanceResult) <= 0)
        echo "<tr><td colspan=7><center>No Instance Yet <a href=page.live.php?action=create_instance>Create One</a></center></td></tr>";
    while ($row = mysqli_fetch_array($instanceResult)) {
        if ($row['cron_flag'] == 0) {
            $pending_flag = 1;
            if ($row['delete_flag'] == 0 && $row['installed'] == 0)
                $status = '<span class="label label-info">Pending Installation</font>';
            else if ($row['delete_flag'] == 1)
                $status = '<span class="label label-danger">Pending Delete</font>';
            else if ($row['status'] == "pending_stop")
                $status = '<span class="label label-info">Pending Stop</span>';
            else if ($row['status'] == "pending_start")
                $status = '<span class="label label-info">Pending Start</span>';
        } else if ($row['cron_flag'] == 1) {

            if ($row['status'] == "stop")
                $status = '<span class="label label-danger">Stopped</span>';
            else if ($row['status'] == "start")
                $status = '<span class="label label-success">Running</span>';
        }

        $path = "/home/" . $row['user_name'] . "/" . $row['domain_name'] . "/tomcat-" . $row['tomcat_version'];


        $path = '<a href="../filemanager/index.html?dir=' . urldecode($path) . '" target="_blank" class="ajaxfiles">' . $path . '</a>';

        echo "<tr><td>$count</td><td><b>Domain Name:</b> " . $row['domain_name'] . "<br/>" . $path . "</td>" . "<td>" . $row['tomcat_version'] . "</td><td>$status</td><td>" . $row['create_date'] . "</td><td>ShutDown Port:" . $row['shutdown_port'] . "<br/>HTTP Port:" . $row['http_port'] . "<br/>AJP Port:" . $row['ajp_port'] . "</td><td>";
        if ($row['status'] == "stop")
            echo "<a href=# class='btn btn-success'  onclick='startTomcatInstance(" . $row['id'] . ")'>Start</a> |";
        if ($row['status'] == "start")
            echo "<a href=#  class='btn btn-warning'  onclick='stopTomcatInstance(" . $row['id'] . ")' >Stop</a> | ";
        if ($row['delete_flag'] == 0) {
            echo " <a class='btn btn-danger' href=#  onclick='deleteTomcatInstance(" . $row['id'] . ")' >Delete</a>  | ";
        }
        echo " <a class='btn btn-primary' href='http://" . $row['domain_name'] . ":" . $row['http_port'] . "/' target=\"_blank\">Visitar</a>";
        echo "</td></tr>";
        $count++;
    }

    echo "</table>";

    if ($pending_flag == 1) {
        ?>
        <div class="alert alert-info" role="alert">
            Instances With Pending Status (installation/stop/start/delete) will be processed to the action within 1
            minute.
        </div>

        <?php
    }
    ?>

    <?php
    echo "<br/><center></center>";

    echo $cpanel->footer();
    ?>


    <script type="text/javascript">
        function startTomcatInstance(id) {
            $.ajax({
                url: 'page.live.php?action=start_tomcat_instance&id=' + id,
                type: 'POST',
                dataType: 'json',
                success: function (data) {
                    if (data['result'] == "success") {
                        alert("Your instance will start shortly");
                        location.reload();
                    } else {
                        alert("Error Occured");
                    }
                }
            });

        }

        function stopTomcatInstance(id) {
            $.ajax({
                url: 'page.live.php?action=stop_tomcat_instance&id=' + id,
                type: 'POST',
                dataType: 'json',
                success: function (data) {
                    if (data['result'] == "success") {
                        alert("Your instance will stop shortly");
                        location.reload();
                    } else {
                        alert("Error Occured");
                    }
                }
            });

        }

        function deleteTomcatInstance(id) {
            if (confirm("Are You Sure You Want to Delete This Instance. Deleting The Instance Will Remove Your all data.")) {
                $.ajax({
                    url: 'page.live.php?action=delete_instance&id=' + id,
                    type: 'POST',
                    dataType: 'json',
                    success: function (data) {
                        if (data['result'] == "success") {
                            alert("SuccesFully Deleted");
                            location.reload();
                        } else {
                            alert("Error Occured");
                        }
                    }
                });
            }
        }
    </script>
    <?php
} else if ($action == "delete_instance") {
    $logger->debug($who . 'DeleteInstance : INSIDE');
    $id = $_GET['id'];
    $tomcat = new \cPanel4jCore\Tomcat();
    $tomcat->deleteInstance($id, $userName);
    $arr = array('result' => "success");
    echo json_encode($arr);
} else if ($action == "create_instance") {
    $logger->debug($who . 'create_instance : INSIDE');
    echo $cpanel->header('cPanel4J');
    $domainListApiCall = $cpanel->api2('DomainLookup', 'getbasedomains', array());
    $domainList = $domainListApiCall['cpanelresult']['data'];
    echo '<p class="lead">cPanel4J allows you to install Apache Tomcat on your domain name.Tomcat is an application server that executes Java Servlets and renders web pages that include JSP Coding.It will work on default 80 port using ajp proxy (httpd as proxy server).</p>';
    echo '<form class="form-horizontal" action = "page.live.php?action=create_instance_action" method = "POST" role="form">';
    echo '<h4>Apache Tomcat Installer</h4><div class="form-group"><label for="domain" class="col-sm-4 control-label">Domain Name</label>';
    echo "<div class='col-sm-8'><select name='domainName' class='form-control'>";
    foreach ($domainList as $domain) {
        echo "<option>" . $domain['domain'] . "</option>";
    }
    echo "</select></div></div>";
    ?>


    <div class="form-group">
        <label for="version" class="col-sm-4 control-label">Tomcat Version</label>
        <div class="col-sm-8"><select name="tomcat-version" class="form-control">
                <option value="7.0.59">7.0.59 (Recommended)</option>
                <option value="8.0.18">8.0.18</option>
            </select></div>
    </div>

    <div class="form-group text-right">
        <input id="next" class="btn btn-primary" type="submit" value="Create Instance">
        <div id="status"></div>
    </div>
    <?php
    echo "</div></form>";
    echo "<br/><center><a href='https://www.cpanel4j.com'>Powered By cPanel4j</a></center>";

    echo $cpanel->footer();
} else if ($action == "create_instance_action") {
    $logger->debug($who . 'create_instance_action : INSIDE');
    $domainName = $_POST['domainName'];
    $tomCatVersion = $_POST['tomcat-version'];
    if (($tomCatVersion == '7.0.59' || $tomCatVersion == '8.0.18') & $domainName != "") {
        $logger->debug($who . 'All Valid creating instance');
        $domainListApiCall = $cpanel->api2('DomainLookup', 'getdocroot', array());
        $domainList = $domainListApiCall['cpanelresult']['data'];
        $domainList = $domainList['0'];
        $docRoot = $domainList['docroot'];
        $roots = explode("/", $docRoot);
        $userName = $roots['2'];
        $tomcat = new \cPanel4jCore\Tomcat();
        $result = $tomcat->createInstance($domainName, $userName, $tomCatVersion);
        $logger->debug($who . 'CreateInstance Result IS'.$result['status']);
        if ($result['status'] == "success"){
            echo '<script> window.location = "page.live.php?action=list"; </script>';
        }
        else if ($result['status'] == "fail") {
            $error = urlencode("This domain already have tomcat instance");
            echo '<script> window.location = "page.live.php?action=create_instance_action&error='.$error.'"</script>';
        }
    } else {
        $logger->debug($who . 'form Data Error : INSIDE');
        echo $cpanel->header('cPanel4J');
        echo "Form Data Error";
    }
    echo "<br/><center></center>";

    echo $cpanel->footer();
} else if ($action == "start_tomcat_instance") {
    $id = $_GET['id'];
    $Tomcat = new \cPanel4jCore\Tomcat();
    $Tomcat->tomcatInstanceAction($id, $userName, "pending_start");
    $arr = array('result' => "success");
    echo json_encode($arr);
} else if ($action == "stop_tomcat_instance") {
    $id = $_GET['id'];
    $Tomcat = new \cPanel4jCore\Tomcat();
    $Tomcat->tomcatInstanceAction($id, $userName, "pending_stop");
    $arr = array('result' => "success");
    echo json_encode($arr);
}
?>
