
{foreach $payment_methods as $method => $name}
<p class="payment_module" id="masterpayment_{$method}">
    <a href="{$mod_dir}backward_compatibility/fc.php?controller=submit&payment_method={$method}">
        <img src="{$mod_dir}views/img/p/{$method}.png" title="{$name}" alt="{$name}" />
        {$name}
    </a>
</p>
{/foreach}
