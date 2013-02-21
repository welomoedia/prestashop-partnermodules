
<h2>{l s='Shopgate' mod='shopgate'}</h2>

<p><img src="{$mod_dir}tpl/logo_web.png"/></p>
<p>{l s='Create fast and easy to own mobile shop. Shopgate developed for you from 19,- EUR a mobile web page and real apps for iPhone, Android, iPad and other mobile systems. About the module, products and inventories with the mobile shop and orders automatically synchronized from the mobile shop in your store Presto transfer. For more information, see' mod='shopgate'} <a href="http://www.shopgate.com" target="_blank">www.shopgate.com</a></p>
<p style="clear: both;">&nbsp;</p>

<script src="../js/jquery/jquery-colorpicker.js" type="text/javascript"></script>
<script type="text/javascript">
{literal}
    function shopgate_settings_toggle_server(obj)
    {
	if($(obj).val() == 'custom')
	    $('#shopgate_server').slideDown();
	else
	    $('#shopgate_server').slideUp();
    }
{/literal}
</script>

<form method="post" action="">
    <fieldset>
	<legend><img title="" alt="" src="{$mod_dir}logo.gif">{l s='Configuration' mod='shopgate'}</legend>

	<h2>{l s='Info' mod='shopgate'}</h2>

	<label>{l s='API URL' mod='shopgate'}</label>
	<div class="margin-form">
	   <input type="text" value="{$api_url}" readonly="readonly" size="60" onclick="$(this).select();" style="background-color: #EFEFEF;"/>
	   <p>{l s='Use this URL in shopgate merchant settings' mod='shopgate'}</p>
	</div>
	
	<label>{l s='Currency' mod='shopgate'}</label>
	<div class="margin-form">
		<select name="configs[currency]">
		{foreach from=$currencies item=currency}
			<option value="{$currency.iso_code|@strtoupper}" {if $currency.iso_code|@strtoupper == $configs.currency}selected="selected"{/if}>{$currency.name}</option>
		{/foreach}
		</select>
	</div>

	<h2>{l s='Basic' mod='shopgate'}</h2>

	<label>{l s='Activate shop' mod='shopgate'}</label>
	<div class="margin-form">
	    <label class="t"><input type="radio" value="true"  name="configs[shop_is_active]"{if $configs.shop_is_active} checked="checked"{/if}/> <img title="{l s='Enabled' mod='shopgate'}" alt="{l s='Enabled' mod='shopgate'}" src="../img/admin/enabled.gif"></label>
	    <label class="t"><input type="radio" value="false" name="configs[shop_is_active]"{if !$configs.shop_is_active} checked="checked"{/if}/> <img title="{l s='Disabled' mod='shopgate'}" alt="{l s='Disabled' mod='shopgate'}" src="../img/admin/disabled.gif"></label>
	</div>
	<label>{l s='Customer number' mod='shopgate'}</label>
	<div class="margin-form">
	    <input type="text" name="configs[customer_number]" value="{$configs.customer_number}" size="4"/>
	</div>
	<label>{l s='Shop number' mod='shopgate'}</label>
	<div class="margin-form">
	    <input type="text" name="configs[shop_number]" value="{$configs.shop_number}" size="4"/>
	</div>
	<label>{l s='Api key' mod='shopgate'}</label>
	<div class="margin-form">
	    <input type="text" name="configs[apikey]" value="{$configs.apikey}" size="20"/>
	</div>
	<label>{l s='Language' mod='shopgate'}</label>
	<div class="margin-form">
	    {html_options name='configs[language]' options=$langs selected=$configs.language}
	</div>
	<label>{l s='Default shipping service' mod='shopgate'}:</label>
	<div class="margin-form">
	    {html_options name='settings[SHOPGATE_SHIPPING_SERVICE]' options=$shipping_service_list selected=$settings['SHOPGATE_SHIPPING_SERVICE']}
	</div>
	

	<h2>{l s='Server' mod='shopgate'}</h2>

	<label>{l s='Server' mod='shopgate'}</label>
	<div class="margin-form">
	    {html_options name='configs[server]' options=$servers selected=$configs.server onchange="shopgate_settings_toggle_server(this);"}
	</div>
	<div id="shopgate_server" {if $configs.server !='custom'}style="display:none;"{/if}>
	    <label>{l s='Custom API URL' mod='shopgate'}</label>
	    <div class="margin-form">
		<input type="text" name="configs[api_url]" value="{$configs.api_url}" size="40"/>
	    </div>
	</div>
	<h2>{l s='Enable' mod='shopgate'}</h2>

	<label>{l s='Minimum quantity check' mod='shopgate'}</label>
	<div class="margin-form">
	    <label class="t"><input type="radio" value="1" name="settings[SHOPGATE_MIN_QUANTITY_CHECK]"{if $settings.SHOPGATE_MIN_QUANTITY_CHECK} checked="checked"{/if}/> <img title="{l s='Enabled' mod='shopgate'}" alt="{l s='Enabled' mod='shopgate'}" src="../img/admin/enabled.gif"></label>
	    <label class="t"><input type="radio" value="0" name="settings[SHOPGATE_MIN_QUANTITY_CHECK]"{if !$settings.SHOPGATE_MIN_QUANTITY_CHECK} checked="checked"{/if}/> <img title="{l s='Disabled' mod='shopgate'}" alt="{l s='Disabled' mod='shopgate'}" src="../img/admin/disabled.gif"></label>
	</div>
	<label>{l s='Out of stock check' mod='shopgate'}</label>
	<div class="margin-form">
	    <label class="t"><input type="radio" value="1" name="settings[SHOPGATE_OUT_OF_STOCK_CHECK]"{if $settings.SHOPGATE_OUT_OF_STOCK_CHECK} checked="checked"{/if}/> <img title="{l s='Enabled' mod='shopgate'}" alt="{l s='Enabled' mod='shopgate'}" src="../img/admin/enabled.gif"></label>
	    <label class="t"><input type="radio" value="0" name="settings[SHOPGATE_OUT_OF_STOCK_CHECK]"{if !$settings.SHOPGATE_OUT_OF_STOCK_CHECK} checked="checked"{/if}/> <img title="{l s='Disabled' mod='shopgate'}" alt="{l s='Disabled' mod='shopgate'}" src="../img/admin/disabled.gif"></label>
	</div>
	
	
	<h2>{l s='Mobile site' mod='shopgate'}</h2>

	<label>{l s='Activate' mod='shopgate'}</label>
	<div class="margin-form">
	    <label class="t"><input type="radio" value="true"  name="configs[enable_mobile_website]"{if $configs.enable_mobile_website} checked="checked"{/if}/> <img title="{l s='Enabled' mod='shopgate'}" alt="{l s='Enabled' mod='shopgate'}" src="../img/admin/enabled.gif"></label>
	    <label class="t"><input type="radio" value="false" name="configs[enable_mobile_website]"{if !$configs.enable_mobile_website} checked="checked"{/if}/> <img title="{l s='Disabled' mod='shopgate'}" alt="{l s='Disabled' mod='shopgate'}" src="../img/admin/disabled.gif"></label>
	</div>
	<label>{l s='Alias' mod='shopgate'}</label>
	<div class="margin-form">
	    <input type="text" name="configs[alias]" value="{$configs.alias}" size="16"/>
	</div>
	<label>{l s='CName' mod='shopgate'}</label>
	<div class="margin-form">
	    <input type="text" name="configs[cname]" value="{$configs.cname}" size="16"/>
	</div>

	<center><input class="button" type="submit" value="{l s='Save' mod='shopgate'}" name="saveConfigurations"></center>
</fieldset>