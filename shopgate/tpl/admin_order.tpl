<br />
<form action="" method="post">
<fieldset style="width: 400px">
    <legend><img src="{$mod_dir}/logo.gif">{l s='Shopgate information' mod='shopgate'}</legend>
    
    {if $shopgate_error}<span style="color:red; font-weight:bold;">{$shopgate_error}</span>{/if}
    
    {if $order}
    <label>{l s='Order number' mod='shopgate'}:</label>
    <div class="margin-form">
	<a href="{$order->getConfirmShippingUrl()}" target="_blank">{$shopgateOrder->order_number} <img src="../img/admin/arrow.gif"></a>
    </div>   <label>{l s='Paid' mod='shopgate'}:</label>
    <div class="margin-form">
	<img src="../img/admin/{if $order->getIsPaid()}enabled{else}disabled{/if}.gif">
    </div>   
    <label>{l s='Shipping blocked' mod='shopgate'}:</label>
    <div class="margin-form">
	<img src="../img/admin/{if $order->getIsShippingBlocked()}enabled{else}disabled{/if}.gif">
    </div>    
    <label>{l s='Delivered' mod='shopgate'}:</label>
    <div class="margin-form">
	<img src="../img/admin/{if $order->getIsShippingCompleted()}enabled{else}disabled{/if}.gif">
    </div>
    {if $order->getPaymentTransactionNumber()}
    <label>{l s='Payment Transaction Number' mod='shopgate'}:</label>
    <div class="margin-form">
	{$order->getPaymentTransactionNumber()}
    </div>    
    {/if}
    
    <h4 style="border-bottom:1px solid #E0D0B1">{l s='Payment information' mod='shopgate'}</h4>
    {if count($order->getPaymentInfos())}
	{foreach $order->getPaymentInfos() as $key => $data}
	<label>{if isset($paymentInfoStrings[$key])}{$paymentInfoStrings[$key]}{else}{$key}{/if}:</label>
	<div class="margin-form">
	    {if is_bool($data)}<img src="../img/admin/{if $data}enabled{else}disabled{/if}.gif">{else}{$data}{/if}
	</div>
	{/foreach}    
    {/if}
    
    <h4 style="border-bottom:1px solid #E0D0B1">{l s='Delivery notes' mod='shopgate'}</h4>
    
    {if count($order->getDeliveryNotes())}
    <table class="table" cellspacing="0" cellpadding="0" style="width:400px">
	<tr>
	    <th>{l s='Service' mod='shopgate'}</th>
	    <th>{l s='Tracking number' mod='shopgate'}</th>
	    <th>{l s='Time' mod='shopgate'}</th>
	</tr>
	{foreach $order->getDeliveryNotes() as $note}
	<tr>
	    <td>{$shipping_service_list[$note->getShippingServiceId()]}</td>
	    <td>{$note->getTrackingNumber()}</td>
	    <td>{$note->getShippingTime()}</td>
	</tr>
	{/foreach}
    </table>
    {else}
	{l s='No delivery notes' mod='shopgate'}
    {/if}
    

    <h4 style="border-bottom:1px solid #E0D0B1">{l s='Shipping settings' mod='shopgate'}</h4>

    <label>{l s='Shipping service' mod='shopgate'}:</label>
    <div class="margin-form">
	{html_options name='shopgateOrder[shipping_service]' options=$shipping_service_list selected=$shopgateOrder->shipping_service}
    </div>
    <label>{l s='Tracking number' mod='shopgate'}:</label>
    <div class="margin-form">
	<input type="text" name="shopgateOrder[tracking_number]" value="{$shopgateOrder->tracking_number}">
    </div>
    <div class="margin-form">
	<input type="submit" class="button" name="updateShopgateOrder" value="{l s='Save' mod='shopgate'}">
    </div>
    
    {elseif !$shopgate_error}
	<span style="color:red; font-weight:bold;">{l s='Order not found in shopgate' mod='shopgate'}</span>
    {/if}
</fieldset>
</form>