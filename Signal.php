<?php
/**
 * An Event Dispatching mechanism
 */

/**
 *
 */
defined('BASEPATH') OR exit('No direct script access allowed');

require_once('SignalException.php');
require_once('signal_tools.php');


/**
 * An event(signal) system for CI.
 *
 * The term event and signal are used to mean the same thing in this class.
 *
 * This class listens for signals that occur within an application and
 * notifies some other part of the system that a the signal has occurred.
 *
 * <h4>Example</h4>
 * This maybe useful e.g. if you want to send email each time a new user is registered.
 *
 * <h4><strong>Loading The Library</strong></h4>
 *
 * To start using the signal dispatcher, load it like any other CODIGNTER library, preferably using autoload
 * <pre><code>$autoload['libraries'] = array(
 *          'powerdispatch/signal',<------------------------------------ the dispatcher
 *          'powerorm/orm',
 *          'powerauth/auth'
 * );</code></pre>
 *
 * <h4><strong>The Sender</strong></h4>
 * For this to happen the user registration mechanism has to issue some signals to notify
 * other parts of the system/application it has registered a user,
 *
 * So in this case the user registration mechanism is called the <strong>sender</strong>. i.e it sends signals
 *
 * To send a signal you use the {@see Signal::dispatch()} method of the Signal class as shown in the example below
 *
 * <pre><code>class User_model extends Base_model{
 *              ... // other methods
 *
 *      public function save($args){
 *              ... // saving logic
 *
 *          $saved_user = ...
 *
 *          // notify everyone that is listening that a user has been registered
 *          $this->signal->dispatch('powerorm.model.post_save', get_class($this), $saved_user);
 *      }
 *
 * }
 *
 * class User_controller extends CI_Controller{
 *          ... // logic to save user
 *          $this->user_model->save($args);
 *
 *
 *
 * }</code></pre>
 *
 * <h4><strong>Signal</strong></h4>
 * A signal just a PHP string,
 * When coming up with a signal name try to make them a unique as possible to avoid collisions with other
 * parts of the application dispatching a signal having the same string for a signal.
 *
 * e,g the one i have used 'powerorm.model.post_save' in the example. in which my models leave in a
 * package called `powerorm`
 *
 * <h4><strong>The Receiver</strong></h4>
 *
 * Once the user registration mechanism has issued a notification that it has registered a user, The 'Signal' class,
 * will notify all other parts of the application/system of this notification.
 *
 * For any part of the system/application to be notified of this signals,
 * it has to register with the `Signal` class.
 *
 * Once registered it becomes a <strong>receiver</strong> i.e. it receives signals.
 *
 * A receiver can be any PHP function or method that is it returns true when tested by is_callable()
 * and callable via call_user_func()
 *
 * <h4><strong>Registering Receivers</strong></h4>
 * To register recievers, create a file `config/signals.php` add create and array of receivers.
 *
 * Just like you would CI HOOKS
 *
 * <strong>Take Note of </strong> <pre><code>$receivers['model.post_save'][] = ... </code></pre>
 *
 * This allows for multiple receivers to listen for the same signal.
 * The order you define your array will be the execution order.
 *
 * Continuing with our example the first two receivers will be notified when the registration mechanism registers a user.
 * Which will the perform some actions
 *
 * <pre><code>// class method
 *
 * $receivers['powerorm.model.post_save'][] = array(
 *      'class'    => 'Authauth', // the class to be invoked, leave blank if you just using a function
 *      'function' => 'auth_check', // function / method to be called
 *      'filename' => 'Authauth.php', // the file name containing the function or class with the method to be called
 *      'filepath' => 'libraries' // the directory where the file is located relative to the application directory.
 * );
 *
 * // function
 *
 * $receivers['powerorm.model.post_save'][] = array(
 *      'class'    => '',
 *      'function' => 'email',
 *      'filename' => 'sender.php',
 *      'filepath' => 'libraries'
 *      'sender' => 'user_model' // in this case we only want to listen for when user_model issues a `model.post_save` signal
 * );
 *
 * // closure function
 *
 * $receivers['powerorm.model.pre_save'][] =function($sender, $params){

 * };
 *
 * // closure function
 * // not here that only one  receiver is listening for this signal
 * $receivers['powerauth.auth.login_success'] =function($sender, $params){
 *
 * };</code></pre>
 *
 *
 * <h4> How it works </h4>
 *
 * When a sender issues a signal, all the registered receivers are notified, the receivers the performs some action
 *
 * <h4> Important Things To Note </h4>
 *
 * - Does not replace the CI Hooks but Works parallel to Hooks.
 *
 * - Load it early enough to be able to catch all signals.
 * I recommend use ci autoload and make the first in the libraries array.
 *
 * <pre><code>$autoload['libraries'] = array(
 *      'powerdispatch/signal',
 *      'session',
 *      'powerorm/orm',
 *      'powerauth/auth'
 * );</code></pre>
 *
 * <h4>Similarity from CI HOOKS</h4>
 *
 * - Receivers are registered in a config file called `config/signals.php`
 * - Registering a receiver requires same amount paramaters as hooks
 *
 *
 * <h4>Difference from CI HOOKS</h4>
 *
 * - A receiver can register to listen for specific senders only. see receiver example above
 *
 * - The receiver method get two mandatory arguments
 *      - sender -- this is the object that sent the signal
 *      - params -- This are extra arguments sent to the receiver by the sendender,
 *                  while most sender will not send extra arguments the receiver is expected accept this argument
 *                  this because the sender might start sending arguments in future
 *
 *
 * @package POWERCI
 * @author Eddilbert Macharia (http://eddmash.com) <edd.cowan@gmail.com>
 */
class Signal{
    /**
     * Holds all registered receivers
     * @ignore
     * @var array
     */
    private $_receivers = array();

    /**
     * @ignore
     * @var bool
     */
    protected $_in_progress = FALSE;

    /**
     * Array with class objects to use signal methods
     * @ignore
     * @var array
     */
    protected $_objects = array();


    /**
     * @ignore
     */
    public function __construct() {
        log_message('info', sprintf("****************** %s system started ***********************", get_class($this)));

        // load the signal receivers
        include_once(APPPATH."config/signals.php");

        // If there are no signals, we're done.
        if ( ! isset($receivers) OR ! is_array($receivers))
        {
            return;
        }

        $this->_receivers =& $receivers;
    }


    /**
     * Sends signal from sender to all register receivers.
     * @param string $signal the signal to dispatch e.g post_save.
     * make you signals as unique as possible to avoid collusions with other peoples collisions
     * @param mixed $sender the object sendign this signal can be an object or a name
     * @param mixed $params extra params to pass to receiver
     * @return bool
     * @throws SignalException
     */
    public function dispatch($signal, $sender, $params=NULL) {

        if(empty($signal)){
            throw new SignalException("Trying to dispatch a signal without specifying the signal to dispatch");
        }

        if(empty($sender)){
            throw new SignalException(sprintf("The sender of the `%s` has not been provided", $signal));
        }

        // check if any receivers have registered for this signal
        if ( ! isset($this->_receivers[$signal]))
        {
            return FALSE;
        }

        // check the signal receivers are in an array and that they have a function defined
        if (is_array($this->_receivers[$signal]) && ! isset($this->_receivers[$signal]['function']))
        {
            // for each of the registered receivers, invoke them
            // this only happends in this case  $receiver[][]= array();
            foreach ($this->_receivers[$signal] as $receiver)
            {
                $this->_invoke_receiver($sender, $receiver, $params);
            }
        }
        else
        {
            // this only happens in this case  $receiver[]= array();
            $this->_invoke_receiver($sender, $this->_receivers[$signal]);
        }

        return TRUE;
    }

    /**
     * Does the actual calls of the receivers.
     * @ignore
     * @param $sender
     * @param $receiver
     * @param $params
     * @return bool|void
     */
    protected function _invoke_receiver($sender, $receiver,  $params){

        // Closures/lambda functions and array($object, 'method') callables
        if (is_callable($receiver))
        {
            is_array($receiver)
                ? $receiver[0]->{$receiver[1]}($sender, $params)
                : $receiver($sender, $params);

            return TRUE;
        }

        // if its not an array dont process further,
        // we need this to be array with all the registration details of a receiver see config/signals.php
        if ( ! is_array($receiver))
        {
            return FALSE;
        }

        // -----------------------------------
        // Safety - Prevents run-away loops
        // -----------------------------------

        // If the script being called happens to have the same
        // hook call within it a loop can happen
        if ($this->_in_progress === TRUE)
        {
            return;
        }

        // -----------------------------------
        // Set file path
        // -----------------------------------

        if ( ! isset($receiver['filepath'], $receiver['filename']))
        {
            return FALSE;
        }

        $filepath = APPPATH.$receiver['filepath'].'/'.$receiver['filename'];


        if ( ! file_exists($filepath))
        {
            return FALSE;
        }

        // Determine and class and/or function names
        $class	= empty($receiver['class']) ? FALSE : $receiver['class'];
        $function = empty($receiver['function']) ? FALSE : $receiver['function'];
        $sender_to_listen_for = isset($data['sender']) ? $receiver['sender'] : '';

        // if dont have a function stop processing
        if (empty($function))
        {
            return FALSE;
        }

        // if receiver only wants to listen for a particular sender
        if(!empty($sender_to_listen_for)){
            // get sender name
            $sender_name = $sender;
            if(is_object($sender)){
                $sender_name = get_class($sender);
            }


            // if this sender is not the sender the receiver was hoping for just exist
            if($sender_name !== $sender_to_listen_for){
                return FALSE;
            }
        }

        // Set the _in_progress flag
        $this->_in_progress = TRUE;

        // If receiver is a class method
        if ($class !== FALSE)
        {
            // The object is stored?
            if (isset($this->_objects[$class]))
            {
                if (method_exists($this->_objects[$class], $function))
                {
                    // invoke the class method
                    $this->_objects[$class]->$function($sender, $params);
                }
                else
                {
                    return $this->_in_progress = FALSE;
                }
            }
            else
            {
                // load the class
                class_exists($class, FALSE) OR require_once($filepath);

                // if still the class does not exist or it does not have the requested method
                if ( ! class_exists($class, FALSE) OR ! method_exists($class, $function))
                {
                    return $this->_in_progress = FALSE;
                }

                // create instance of the class
                // Store the object and execute the method
                $this->_objects[$class] = new $class();
                $this->_objects[$class]->$function($sender, $params);
            }
        }
        else
        {   // if receiver is a normal PHP function
            function_exists($function) OR require_once($filepath);

            if ( ! function_exists($function))
            {
                return $this->_in_progress = FALSE;
            }

            // invoke it
            call_user_func($function, $params);
//            $function($sender, $params);
        }

        $this->_in_progress = FALSE;
        return TRUE;
    }
}
