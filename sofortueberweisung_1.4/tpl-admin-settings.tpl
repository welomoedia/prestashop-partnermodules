<h2>{$lDisplayName}</h2>

<form action="{$url}" method="post">
<fieldset style="margin-bottom: 10px;">
    <legend><img src="../img/admin/prefs.gif" />{l s='sofortüberweisung.de Configuration' mod='sofortueberweisung'}</legend>
    <label>{l s='User ID' mod='sofortueberweisung'}</label>
    <div class="margin-form">
        <input type="text" name="su_user_id" value="{$value_su_user_id}" />
    </div>
    <label>{l s='Project ID' mod='sofortueberweisung'}</label>
    <div class="margin-form">
        <input type="text" name="su_project_id" value="{$value_su_project_id}" />
    </div>
    <label>{l s='Project password' mod='sofortueberweisung'}</label>
    <div class="margin-form">
        <input type="text" name="su_project_password" value="{$value_su_project_password}" />
    </div>
    <label>{l s='Notification password' mod='sofortueberweisung'}</label>
    <div class="margin-form">
        <input type="text" name="su_notification_password" value="{$value_su_notification_password}" />
    </div>
</fieldset>
<fieldset style="margin-bottom: 10px;">
    <legend><img src="../img/admin/information.png" />{l s='Configuration of sofortüberweisung.de backend' mod='sofortueberweisung'}</legend>
	<p>{l s='Configure your sofortüberweisung.de backend like shown on the screens below. Use following URLs:' mod='sofortueberweisung'}</p>
    <label>{l s='Success link' mod='sofortueberweisung'}</label>
    <div class="margin-form" style="font-size: inherit; padding-top: 3px;">
        {$domain}success.php?cartid=-USER_VARIABLE_0-
    </div>
    <label>{l s='Abort link' mod='sofortueberweisung'}</label>
    <div class="margin-form" style="font-size: inherit; padding-top: 3px;">
        {$domain}cancel.php
    </div>
    <label>{l s='Notification URL (Method: POST)' mod='sofortueberweisung'}</label>
    <div class="margin-form" style="font-size: inherit; padding-top: 3px;">
        {$domain}notify.php
    </div>
	
	<div style="margin-top: 30px;">
		<p><small>{l s='Fig. 1: This URLs must be set in My Projects > [Project Name] > Interface' mod='sofortueberweisung'}</small></p>
		<img style="border: 1px solid black;" src="../modules/sofortueberweisung/img/su-backend-01.jpg" />
	</div>
	<div style="margin-top: 5px;">
		<p><small>{l s='Fig. 2: To create a new notification go to My Projects > [Project Name] > Tab Extended Settings > Notifications > add new notification / edit existent notification > Tab HTTP. Then enter the notification URL and save your new notification. Method must be always set to "POST".' mod='sofortueberweisung'}</small></p>
		<img style="border: 1px solid black;" src="../modules/sofortueberweisung/img/su-backend-02.jpg" />
	</div>
</fieldset>
<p>
    <input type="submit" value="Update" class="button" />
</p>
<input type="hidden" name="su_posted" value="1" />
</form>