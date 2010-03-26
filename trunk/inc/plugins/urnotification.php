<?php
/**
 * License: http://www.mybboard.net/about/license
 *
 * $Id: urnotification.php 0.1 2010-03-24 17:30:00 Jayant and Abhijeet $
 */

if(!defined("IN_MYBB"))
{
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


// Set the checkbox selected state while rendering it in user cp.
$plugins->add_hook("usercp_options_start", "urnotification_setcheckboxstate");

// Handle the checkbox input when user saves modifications in user cp.
$plugins->add_hook("datahandler_user_update", "urnotification_update");

// Send out notifications to willing referenced users when a new reply is made.
$plugins->add_hook("newreply_do_newreply_end", "urnotification_notifyusers");

// TODO: Attach event handlers to the new reply text box and the ajax reply textbox.
// The javascript is already in place (jscripts/urnotification.js). Figure out the hooks and insert javascript at end of appropriate page.
// init() function in Javascript may require a change


// TODO: for some reason these variables dont work when used in functions below.
$template_search_text = '{$lang->pm_notify}</label></span></td>';
$template_replacement_text = '{$lang->pm_notify}</label></span></td>
</tr>
<tr>
<td valign="top" width="1"><input type="checkbox" class="checkbox" name="urnotify" id="urnotify" value="1" {$urnotifycheck} /></td>
<td><span class="smalltext"><label for="urnotify">Notify me by email when @myusername is referenced in a post.</label></span></td>
</tr>';

function urnotification_info()
{
  return array(
    "name"           => "Username Reference Notification",
    "description"    => "Notifies user by email when @username is referenced in a post",
    "website"        => "http://abhijeetmaharana.com",
    "author"         => "Jayant and Abhijeet",
    "authorsite"     => "http://abhijeetmaharana.com",
    "version"        => "0.1",
    "guid"           => "cef01134-576d-4114-b874-e957e90662b4",
    "compatibility"  => "14*"   // Need to check up on the accepted values in detail
  );
}

function urnotification_activate()
{
  global $db;

  /* TODO: Should the possibility be added to disable this from admin cp? */
  $db->query("ALTER TABLE ".TABLE_PREFIX."users ADD allowurnotifications INT(1) NOT NULL AFTER pmnotify");

  /* TODO: Language support? */
  require_once MYBB_ROOT."inc/adminfunctions_templates.php";

  /* Insert fields into usercp for editing */

  find_replace_templatesets('usercp_options',preg_quote('#{$lang->pm_notify}</label></span></td>#'),
         '{$lang->pm_notify}</label></span></td>
</tr>
<tr>
<td valign="top" width="1"><input type="checkbox" class="checkbox" name="urnotify" id="urnotify" value="1" {$urnotifycheck} /></td>
<td><span class="smalltext"><label for="urnotify">Notify me by email when @myusername is referenced in a post.</label></span></td>
</tr>');
}

function urnotification_deactivate()
{
  global $db;

  $db->query("ALTER TABLE ".TABLE_PREFIX."users DROP COLUMN allowurnotifications");

  require_once MYBB_ROOT."inc/adminfunctions_templates.php";

  find_replace_templatesets('usercp_options',
      preg_quote('#{$lang->pm_notify}</label></span></td>
</tr>
<tr>
<td valign="top" width="1"><input type="checkbox" class="checkbox" name="urnotify" id="urnotify" value="1" {$urnotifycheck} /></td>
<td><span class="smalltext"><label for="urnotify">Notify me by email when @myusername is referenced in a post.</label></span></td>
</tr>#'),
      '{$lang->pm_notify}</label></span></td>',0);

}

function urnotification_update($tnotify)
{
  global $mybb;

  if (isset($mybb->input['urnotify']))
  {
      $tnotify->user_update_data['allowurnotifications'] = 1;
  }
  else
  {
      $tnotify->user_update_data['allowurnotifications'] = 0;
  }
}

// Declare this to be global since it needs to be EVALed within the template
$urnotifycheck = '';

function urnotification_setcheckboxstate()
{
    global $mybb, $errors, $urnotifycheck;

    /* Get hold of user settings.
     *
     * Global $user hasn't been initialized yet. So can't use it.
     * Can't use usercp_options_end as well since the template is EVALed before the hook is executed.
     *
     * Below 'if' is from usercp.php.
     */

    if($errors != '')
    {
        $user = $mybb->input;
    }
    else
    {
        $user = $mybb->user;
    }

    // Now check whats the current setting for allowing urnotifications.
    if($user['allowurnotifications'] == 1)
    {
        $urnotifycheck = " checked=\"checked\"";
    }
    else
    {
        $urnotifycheck = "";
    }
}


function urnotification_notifyusers()
{
    global $post;
    global $mybb;

    $users = get_users_to_notify();
    if ($users)	{
            notify_users($users);
    }

    //$post['message'] = "<strong>{$str}</strong><br /><br />{$post['message']}";
}

/**
* Parses the post to collect usernames.
* Runs a query against the database to get valid users registered in the system.
*
* Output:
* 	$users: Array of valid users in the system that were referenced in the post.
* 		'false' if no valid users were found in the database.
*
*/
function get_users_to_notify() {
	global $db, $post;

	$users = array();

	preg_match_all("/@(\\w+)/i", $post['message'], $usersToNotify, PREG_PATTERN_ORDER);
	$usersAsCsv = "";

        // convert all usernames to lowercase
	$lowerUsers = array_change_value_case($usersToNotify[1], CASE_LOWER);
	$usersAsCsv = "'".join("','", $lowerUsers)."'";

	$query = $db->simple_select("users", "username, email", "LOWER(username) IN (".$usersAsCsv.") AND allowurnotifications = 1");

	while($user = $db->fetch_array($query)) {
		array_push($users, $user);
	}

	return $users;
}

/**
* Queues mail for notifying user. Does *not* respect language settings - English only.
*
* Input:
* 	$users: Array of users to notify
* 		 Assumes that each element is a valid user structure
*/
function notify_users($users){

	global $db, $cache, $post;

	foreach ($users as $user){
		$emailsubject = "Someone referenced your username on the forum";
		$emailmessage = get_email_message($user);

		$new_email = array(
			"mailto" => $db->escape_string($user['email']),
			"mailfrom" => '',
			"subject" => $db->escape_string($emailsubject),
			"message" => $db->escape_string($emailmessage),
			"headers" => ''
		);

		$db->insert_query("mailqueue", $new_email);
		$queued_email = 1;
	}

	if($queued_email == 1)
	{
		$cache->update_mailqueue();
	}
}

/**
* Returns the body of the notification message to be sent to the user.
* Customize the message as necessary.
*
* Input:
* 	$user: current user who will receive the notification
*
* Output:
* 	$emailmessage: string containing the body of the message
*/
function get_email_message($user){

	global $mybb, $post, $postinfo;
        $pid = $postinfo['pid'];
        $tid = $post['tid'];

	$emailtemplate = "%s,

	%s has just referenced you in a post on %s.

	To view the post, you can go to the following URL:
	%s/%s

	Thank you,
	%s Staff
	";

	// numbered arguments not working for some reason
	$emailmessage = sprintf($emailtemplate,
		$user['username'],
		$post['username'],
		$mybb->settings['bbname'],
		$mybb->settings['bburl'],
                unhtmlspecialchars(get_post_link($pid, $tid).'#pid'.$pid),
		$mybb->settings['bbname']);		// TODO: need to use numbered arguments to get rid of this duplicate

	return $emailmessage;
}

// from http://www.php.net/manual/en/function.htmlspecialchars.php#45989
// if PHP 5 >= 5.1.0 is available, use "htmlspecialchars_decode" instead
function unhtmlspecialchars( $string )
{
    $string = str_replace ( '&amp;', '&', $string );
    $string = str_replace ( '&#039;', '\'', $string );
    $string = str_replace ( '&quot;', '\"', $string );
    $string = str_replace ( '&lt;', '<', $string );
    $string = str_replace ( '&gt;', '>', $string );

    return $string;
}

/**
 * http://www.php.net/manual/en/function.array-change-key-case.php#88648
 *
 * @brief Returns an array with all values lowercased or uppercased.
 * @return array Returns an array with all values lowercased or uppercased.
 * @param object $input The array to work on
 * @param int $case [optional] Either \c CASE_UPPER or \c CASE_LOWER (default).
 */
function array_change_value_case(array $input, $case = CASE_LOWER) {
    switch ($case) {
        case CASE_LOWER:
            return array_map('strtolower', $input);
            break;
        case CASE_UPPER:
            return array_map('strtoupper', $input);
            break;
        default:
            trigger_error('Case is not valid, CASE_LOWER or CASE_UPPER only', E_USER_ERROR);
            return false;
    }
}
?>
