<?php

$template_id = !empty($_POST['template_id']) ? $_POST['template_id'] : "none";
$html_content = !empty($_POST['html_content']) ? $_POST['html_content'] : "";
$subject = !empty($_POST['mail_subject']) ? $_POST['mail_subject'] : "";
$list_id = !empty($_POST['email_list_id']) ? $_POST['email_list_id'] : "";
$connection_id = !empty($_POST['connection_id']) ? $_POST['connection_id'] : "";
$mail_from = !empty($_POST['mail_from']) ? $_POST['mail_from'] : "";

$test_recipients = !empty($_POST['test_recipients']) ? preg_replace('/\s+/', '', $_POST['test_recipients']) : "";

$persistence_r = new CASHRequest(null);
$persistence_r->startSession();

// send a test email
if (!empty($_POST['action']) && $_POST['action'] == 'dotestsend') {

    $persistence_r->sessionSet('mass_mailing_content', json_encode($_POST));

    // we need to do some direct send here so we're not creating a redundant test email

    $mailing_result = new CASHRequest(
        array(
            'cash_request_type' => 'people',
            'cash_action' => 'buildmailingcontent',
            'template_id' => $template_id,
            'html_content' => $html_content,
            'title' => $subject,
            'subject' => $subject
        )
    );

    $html_content = $mailing_result->response['payload'];
    $test_recipients = explode(",", $test_recipients);
    $recipients = [];

    foreach($test_recipients as $recipient) {
        $recipients[] = [
            'email' => $recipient,
            'name' => 'Test recipient',
            'metadata' => array(
                'user_id' => 0
            )
        ];
    }

    // skip the requests and make the request directly for testing
    CASHSystem::sendMassEmail(
        $cash_admin->effective_user_id,
        $subject." (test)",
        $recipients,
        $html_content, // message body
        $subject, // message subject
        [ // global merge vars
            [
                'name' => 'cash_listid',
                'content' => 1234
            ]
        ],
        [], // local merge vars (per email)
        false,
        true,
        true,
        $mail_from,
        false
    );

    if ($mailing_result) {
        AdminHelper::formSuccess('Test Success. The mail is sent, check it for errors.','/people/mailings/');
    } else {
        AdminHelper::formFailure('Test Error. Something just didn\'t work right.','/people/mailings/');
    }

}
// send the email
if (!empty($_POST['action']) && $_POST['action'] == 'dolivesend') {

    $mailing_result = new CASHRequest(
        array(
            'cash_request_type' => 'people',
            'cash_action' => 'buildmailingcontent',
            'template_id' => $template_id,
            'html_content' => $html_content,
            'title' => $subject,
            'subject' => $subject
        )
    );

    $html_content = $mailing_result->response['payload'];

	$mailing_response = $cash_admin->requestAndStore(
		array(
			'cash_request_type' => 'people', 
			'cash_action' => 'addmailing',
			'user_id' => $cash_admin->effective_user_id,
			'list_id' => $list_id,
			'connection_id' => $connection_id,
			'subject' => $subject,
			'from_name' => $mail_from,
			'html_content' => $html_content
		)
	);

	$mailing_result = $cash_admin->requestAndStore(
		array(
			'cash_request_type' => 'people', 
			'cash_action' => 'sendmailing',
			'mailing_id' => $mailing_response['payload'],
            'test' => 'true'
		)
	);

	if ($mailing_result) {
        $persistence_r->sessionClear('mass_mailing_content');
		AdminHelper::formSuccess('Success. The mail is sent, just kick back and watch.','/people/mailings/');
    } else {
		AdminHelper::formFailure('Error. Something just didn\'t work right.','/people/mailings/');
	}
}

$effective_user = AdminHelper::getPersistentData('cash_effective_user');

$settings_test_object = new CASHConnection(AdminHelper::getPersistentData('cash_effective_user'));
$settings_test_array  = $settings_test_object->getConnectionsByScope('mass_email');

if ($settings_test_array) {
	$cash_admin->page_data['options_people_lists'] = AdminHelper::echoFormOptions('people_lists',0,false,true);
	$cash_admin->page_data['connection_options'] = AdminHelper::echoConnectionsOptions('mass_email',0,true);
}

$user_request = $cash_admin->requestAndStore(
    array(
        'cash_request_type' => 'people', 
        'cash_action' => 'getuser',
        'user_id' => $effective_user
    )
);

$mailing_content = $persistence_r->sessionGet('mass_mailing_content');

// let's just set template vars up here, for persistence' sake
if (!empty($mailing_content)) {
    $values = json_decode($mailing_content, true);

    $cash_admin->page_data['html_content'] = $values['html_content'];
    $cash_admin->page_data['subject'] = $values['mail_subject'];
    $cash_admin->page_data['list_id'] = $values['email_list_id'];
    $cash_admin->page_data['connection_id'] = $values['connection_id'];
    $cash_admin->page_data['test_recipients'] = $values['test_recipients'];
    $cash_admin->page_data['mail_from'] = $values['mail_from'];
}


$cash_admin->page_data['email_address'] = $user_request['payload']['email_address'];

$cash_admin->setPageContentTemplate('people_mailings');
?>