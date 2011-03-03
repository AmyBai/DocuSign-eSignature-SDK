<?php 
/**
 * @copyright Copyright (C) DocuSign, Inc.  All rights reserved.
 *
 * This source code is intended only as a supplement to DocuSign SDK
 * and/or on-line documentation.
 * This sample is designed to demonstrate DocuSign features and is not intended
 * for production use. Code and policy for a production application must be
 * developed to meet the specific data and security requirements of the
 * application.
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */

/*
 * uploads selected template, creates an envelope from it, and sends it.
 */

//========================================================================
// Includes
//========================================================================
include_once 'include/session.php'; // initializes session and provides
include_once 'api/APIService.php';
include 'include/utils.php';

//========================================================================
// Functions
//========================================================================
function voidSampleEnvelope($envelopeID) {
    $dsapi = getAPI();
    $veParams = new VoidEnvelope();
    $veParams->EnvelopeID = $envelopeID;
    $veParams->Reason = "Envelope voided by sender";
    try {
        $status = $dsapi->VoidEnvelope($veParams)->VoidEnvelopeResult;
        if ($status->VoidSuccess) {
            header("Location: sendatemplate.php");
        }
    } catch (SoapFault $e) {
        $_SESSION["errorMessage"] = $e;
        header("Location: error.php");
    }
}

function createSampleEnvelope() {
    
    // Create envelope information
    $envinfo = new EnvelopeInformation();
    $envinfo->Subject = $_POST["subject"];
    $envinfo->EmailBlurb = $_POST["emailblurb"];
    $envinfo->AccountId = $_SESSION["AccountID"];
    
    if ($_POST["reminders"] != null) {
        $remind = new DateTime($_POST["reminders"]);
        $new = new DateTime($_SERVER['REQUEST_TIME']);
        $days = $now->diff($remind)->d;
        if ($envinfo->Notification == null) {
            $envinfo->Notification = new Notification();
        }
        $envinfo->Notification->Reminders = new Reminders();
        $envinfo->Notification->Reminders->ReminderEnabled = true;
        $envinfo->Notification->Reminders->ReminderDelay = $days;
        $envinfo->Notification->Reminders->ReminderFrequency = "2";
    }
    
    if ($_POST["expiration"] != null) {
        $expire = new DateTime($_POST["reminders"]);
        $new = new DateTime($_SERVER['REQUEST_TIME']);
        $days = $now->diff($expire)->d;
        if ($envinfo->Notification == null) {
            $envinfo->Notification = new Notification();
        }
        $envinfo->Notification->Expirations = new Expirations();
        $envinfo->Notification->Expirations->ExpireEnabled = true;
        $envinfo->Notification->Expirations->ExpireDelay = $days;
        $envinfo->Notification->Expirations->ExpireFrequency = "2";
    }
    
    // Get all recipients
    $recipients = constructRecipients();
    
    // Construct the template reference
    $tref = new TemplateReference();
    $tref->TemplateLocation = TemplateLocationCode::Server;
    $tref->Template = $_POST["TemplateTable"];
    $tref->RoleAssignments = createFinalRoleAssignments($recipients);
    $trefs = array($tref);
    
    if (isset($_POST["SendNow"])) {
        sendNow($trefs, $envinfo, $recipients);
    }
    else {
        embedSending($trefs, $envinfo, $recipients);
    }
}

/**
 *	TODO: get list of recipients from table
 */
function constructRecipients() {
    $recipients[] = new Recipient();
    
    $count = count($_POST["RoleName"]);
    for ($i = 1; $i <= $count; $i++) {
        if ($_POST["RoleName"] != null) {
            $r = new Recipient();
            $r->UserName = $_POST["Name"][$i];
            $r->Email = $_POST["RoleEmail"][$i];
            $r->ID = $i;
            $r->RoleName = $_POST["RoleName"][$i];
            $r->Type = RecipientTypeCode::Signer;
            array_push($recipients, $r);
        }
    }
    
    // eliminate 0th element
    array_shift($recipients);
    
    return $recipients;
}

/**
 * Create an array of
 * 
 * @return multitype:TemplateReferenceRoleAssignment
 */
function createFinalRoleAssignments($recipients) {
    $roleAssignments[] = new TemplateReferenceRoleAssignment();
    
    foreach ($recipients as $r) {
        $assign = new TemplateReferenceRoleAssignment();
        $assign->RecipientID = $r->ID;
        $assign->RoleName = $r->RoleName;
        array_push($roleAssignments, $assign);
    }
    
    // eliminate 0th element
    array_shift($roleAssignments);
    
    return $roleAssignments;
}

function loadTemplates() {
    $dsapi = getAPI();
    
    $rtParams = new RequestTemplates();
    $rtParams->AccountID = $_SESSION["AccountID"];
    $rtParams->IncludeAdvancedTemplates = false;
    try {
        $templates = $dsapi->RequestTemplates($rtParams)->RequestTemplatesResult->EnvelopeTemplateDefinition;
    } catch (SoapFault $e) {
        $_SESSION["errorMessage"] = $e;
        header("Location: error.php");
    }
    
    foreach ($templates as $template) {
        echo '<option value="' . $template->TemplateID . '">' .
            $template->Name . "</option>\n";
//        echo $template->TemplateID . " " . $template->Name . "<br />" . "\n";
    }
}

function sendNow($templateReferences, $envelopeInfo, $recipients) {
    $api = getAPI();
    
    $csParams = new CreateEnvelopeFromTemplates();
    $csParams->EnvelopeInformation = $envelopeInfo;
    $csParams->Recipients = $recipients;
    $csParams->TemplateReferences = $templateReferences;
    $csParams->ActivateEnvelope = true;
    try {
        $status = $api->CreateEnvelopeFromTemplates($csParams)->CreateEnvelopeFromTemplatesResult;
        if ($status->Status == EnvelopeStatusCode::Sent) {
            addEnvelopeID($status->EnvelopeID);
            header("Location: getstatusanddocs.php?envelopid=" . $status->EnvelopeID . 
            	"&accountID=" . $envelope->AccountId . "&source=Document");
        }
    } catch (SoapFault $e) {
        $_SESSION["errorMessage"] = array($e, $api->__getLastRequest(), $csParams);
        header("Location: error.php");
    }
}

function embedSending($templateReferences, $envelopeInfo, $recipients) {
    $api = getAPI();
    
    $ceParams = new CreateEnvelopeFromTemplates();
    $ceParams->EnvelopeInformation = $envelopeInfo;
    $ceParams->Recipients = $recipients;
    $ceParams->TemplateReferences = $templateReferences;
    $ceParams->ActivateEnvelope = false;
    try {
        $status = $api->CreateEnvelopeFromTemplates($ceParams)->CreateEnvelopeFromTemplatesResult;
        if ($status->Status == EnvelopeStatusCode::Created) {
            $rstParam = new RequestSenderToken();
            $rstParam->AccountID = $envelopeInfo->AccountId;
            $rstParam->EnvelopeID = $status->EnvelopeID;
            $rstParam->ReturnURL = getCallbackURL("getstatusanddocs.php");
            addEnvelopeID($status->EnvelopeID);
            $_SESSION["embedToken"] = $api->RequestSenderToken($rstParam)->RequestSenderTokenResult;
            header("Location: embedsending.php?envelopid=" . $status->EnvelopeID . 
            	"&accountID=" . $envelope->AccountId . "&source=Document");
        }
    } catch (SoapFault $e) {
        $_SESSION["errorMessage"] = $e;
        header("Location: error.php");
    }
    
}

//========================================================================
// Main
//========================================================================
loginCheck();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    createSampleEnvelope();
}
else if ($_SERVER["REQUEST_METHOD"] == "GET") {
    ;
}
?>

<!DOCTYPE html">
<html>
    <head>
    <link rel="stylesheet" href="css/jquery.ui.all.css" />
    <link rel="Stylesheet" href="css/SendTemplate.css" />
    <script type="text/javascript" src="js/jquery-1.4.4.js"></script>
    <script type="text/javascript" src="js/jquery.ui.core.js"></script>
    <script type="text/javascript" src="js/jquery.ui.widget.js"></script>
    <script type="text/javascript" src="js/jquery.ui.datepicker.js"></script>
    <script type="text/javascript" src="js/jquery.ui.dialog.js"></script>
    <script type="text/javascript" src="js/jquery.bgiframe-2.1.2.js"></script>
    <script type="text/javascript" src="js/jquery.ui.mouse.js"></script>
    <script type="text/javascript" src="js/jquery.ui.draggable.js"></script>
    <script type="text/javascript" src="js/jquery.ui.position.js"></script>
    <script type="text/javascript" src="js/Utils.js"></script>
    <script type="text/javascript" charset="utf-8">
        $(function () {
            var today = new Date().getDate();
            $("#reminders").datepicker({
                showOn: "button",
                buttonImage: "images/calendar-blue.gif",
                buttonImageOnly: true,
                minDate: today
            });
            $("#expiration").datepicker({
                showOn: "button",
                buttonImage: "images/calendar-blue.gif",
                buttonImageOnly: true,
                minDate: today
            });
            $("#dialogmodal").dialog({
                height: 350,
                modal: true,
                autoOpen: false
            });
            $(".switcher li").bind("click", function () {
            var act = $(this);
            $(act).parent().children('li').removeClass("active").end();
            $(act).addClass("active");
            });
        });

    </script>
	</head>
    <body>
    <nav class="tabs">
    	<a href="senddocument.php">Send Document</a>
    	<a href="sendatemplate.php" class="current">Send a Template</a>
    	<a href="embeddocusign.php">Embed Docusign</a>
    	<a href="getstatusanddocs.php">Get Status and Docs</a>
	</nav>
    <form id="SendTemplateForm" enctype="multipart/form_data" method="post">
    <div>
        <input id="subject" name="subject" placeholder="<enter the subject>" type="text"
            class="email" /><img alt="" src="" class="helplink" /><br />
        <textarea id="emailblurb" cols="20" name="emailblurb" placeholder="<enter the e-mail blurb>"
            rows="4" class="email"></textarea>
    </div>
    <div>
        Select a Template<br />
        <select id="TemplateTable" name="TemplateTable" >
        	<?php loadTemplates(); ?>
        </select>
<!--         <input type="button" id="selectTemplateButton" name="selectTemplateButton"
            value="Go"  /> 
-->
    </div>
    <br />
    <div>
        <table width="100%" id="RecipientTable" name="RecipientTable" >
            <tr class="rowheader">
                <th class="fivecolumn">
                    <b>Role Name</b>
                </th>
                <th class="fivecolumn">
                    <b>Name</b>
                </th>
                <th class="fivecolumn">
                    <b>E-mail</b>
                </th>
                <th class="fivecolumn">
                    <b>Security</b>
                    <img alt="" src="" class="helplink" />
                </th>
                <th class="fivecolumn">
                    <b>Send E-mail Invite</b>
                </th>
            </tr>
        </table>
         <input type="button" onclick="addRoleRowToTable()" value="Add Role"/>
    </div>
    <div>
        <table width="100%">
            <tr class="rowbody">
                <td class="fourcolumn">
                </td>
                <td class="fourcolumn">
                    <input type="text" id="reminders" name="reminders" class="datepickers" />
                </td>
                <td class="fourcolumn">
                    <input type="text" id="expiration" name="expiration" class="datepickers" />
                </td>
                <td class="fourcolumn">
                </td>
            </tr>
            <tr>
                <td class="fourcolumn">
                </td>
                <td class="fourcolumn">
                    Add Daily Reminders
                </td>
                <td class="fourcolumn">
                    Add Expiration
                </td>
                <td class="fourcolumn">
                </td>
            </tr>
            <tr>
                <td class="fourcolumn">
                </td>
                <td class="leftbutton">
                    <input type="submit" value="Send Now" name="SendNow" align="right" style="width: 100%;"
                        class="docusignbutton blue" />
                </td>
                <td class="rightbutton">
                    <input type="submit" value="Edit Before Sending" name="EditFirst" align="left" style="width: 100%;"
                        class="docusignbutton blue" />
                </td>
                <td class="fourcolumn">
                </td>
            </tr>
        </table>
    </div>
    </form>
    <table align="center" style="padding-top: 20px;">
        <tr>
            <td align="center">
                <span>Do you find this sample useful? Tell your friends!</span><br />
                <div class="addthis_toolbox addthis_default_style" style="margin-right: auto; margin-left: auto;
                    width: 210px;">
                    <a class="addthis_button_email"></a><a class="addthis_button_tweet" tw:url="http://www.docusign.com/developers-center/"
                        tw:text="I just tried out the DocuSign API!" tw:via="DocuSignAPI" tw:count="none"
                        tw:related="DocuSign:DocuSign, Inc"></a><a class="addthis_button_delicious">
                    </a><a class="addthis_button_stumbleupon"></a><a class="addthis_button_facebook_like"
                        fb:href="http://www.docusign.com/devcenter/"></a>
                </div>
            </td>
        </tr>
        <tr>
            <td align="center">
                <span>Keep up with new developments:</span><br />
                <a class="addthis_email" href="http://www.docusign.com/blog">
                    <img src="images/blog.png" width="16" height="16" border="0" alt="Blog" /></a>
                <a class="addthis_email" href="http://www.youtube.com/user/ESIGNwithDocuSign">
                    <img src="images/icon-youtube.png" width="16" height="16" border="0" alt="Youtube" /></a>
                <a class="addthis_email" href="http://www.docusign.com/blog/feed/">
                    <img src="images/icon-rss.png" width="16" height="16" border="0" alt="RSS" /></a>
                <a class="addthis_email" href="http://www.facebook.com/pages/DocuSign/71115427991">
                    <img src="images/icon-facebook.png" width="16" height="16" border="0" alt="Facebook" /></a>
                <a class="addthis_email" href="http://www.twitter.com/DocuSign">
                    <img src="images/icon-twitter.png" width="16" height="16" border="0" alt="Twitter" /></a>
                <a class="addthis_email" href="http://www.linkedin.com/company/19022?trk=saber_s000001e_1000">
                    <img src="images/icon-linkedin.png" width="16" height="16" border="0" alt="LinkedIn" /></a>
            </td>
        </tr>
	</table>
	</body>
</html>
