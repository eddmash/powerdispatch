<?php
if(!function_exists('dispatch_signal')):
function dispatch_signal($signal, $sender){
    $_ci =& get_instance();

    if(isset($_ci->signal) && is_object($_ci->signal) && $_ci->signal instanceof Signal):
        $_ci->signal->dispatch($signal, $sender);
    endif;
}
endif;