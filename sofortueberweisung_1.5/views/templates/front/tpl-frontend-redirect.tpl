<h2>{l s='Payment through sofortüberweisung.de' mod='sofortueberweisung'}</h2>
<p>{l s='You are now being redirected to the sofortüberweisung.de payment page...' mod='sofortueberweisung'}</p>
<form action="https://www.sofortueberweisung.de/payment/start" method="post" id="sofortueberweisungForm">
    <input type="hidden" name="user_id" value="{$user_id}">
    <input type="hidden" name="project_id" value="{$project_id}">
    <input type="hidden" name="amount" value="{$amount}">
    <input type="hidden" name="reason_1" value="{$reason_1}">
    <input type="hidden" name="reason_2" value="{$reason_2}">
    <input type="hidden" name="user_variable_0" value="{$user_variable_0}">
    <input type="hidden" name="currency_id" value="{$currency_id}">
    <input type="hidden" name="language_id" value="{$language_id}">
    <input type="hidden" name="hash" value="{$hash}">
    <input type="submit" value="Klick Hier">
</form>

<script type="text/javascript">
// <![CDATA[
document.getElementById('sofortueberweisungForm').submit();
//]]>
</script>