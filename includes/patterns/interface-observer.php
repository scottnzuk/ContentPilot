<?php
/**
 * Observer Pattern Implementation for Event-Driven Architecture
 *
 * @package AI_Auto_News_Poster\Patterns
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subject Interface
 * 
 * Defines the contract for subjects that observers can subscribe to
 */
interface AANP_Subject_Interface {
    
    /**
     * Attach an observer
     * 
     * @param AANP_Observer_Interface $observer Observer to attach
     * @return bool True if successfully attached
     */
    public function attach(AANP_Observer_Interface $observer);
    
    /**
     * Detach an observer
     * 
     * @param AANP_Observer_Interface $observer Observer to detach
     * @return bool True if successfully detached
     */
    public function detach(AANP_Observer_Interface $observer);
    
    /**
     * Notify all observers
     * 
     * @param string $event Event name
     * @param array $data Event data
     * @return array Results from all observers
     */
    public function notify($event, $data = array());
    
    /**
     * Get attached observers
     * 
     * @return array Array of attached observers
     */
    public function get_observers();
}

/**
 * Observer Interface
 * 
 * Defines the contract for observers
 */
interface AANP_Observer_Interface {
    
    /**
     * Update method called when subject notifies
     * 
     * @param string $event Event name
     * @param array $data Event data
     * @param AANP_Subject_Interface $subject Subject that triggered the event
     * @return mixed Observer response
     */
    public function update($event, $data, AANP_Subject_Interface $subject);
    
    /**
     * Get observer priority
     * 
     * @return int Priority (higher numbers execute first)
     */
    public function get_priority();
    
    /**
     * Check if observer is interested in event
     * 
     * @param string $event Event name
     * @return bool True if interested
     */
    public function is_interested_in($event);
}

/**
 * Event-Driven Subject Implementation
 */
class AANP_Event_Subject implements AANP_Subject_Interface {
    
    /**
     * Attached observers
     * @var array
     */
    private $observers = array();
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
    }
    
    /**
     * Attach an observer
     * 
     * @param AANP_Observer_Interface $observer
     * @return bool
     */
    public function attach(AANP_Observer_Interface $observer) {
        try {
            // Check if observer is already attached
            foreach ($this->observers as $existing_observer) {
                if ($existing_observer === $observer) {
                    $this->logger->warning('Observer already attached', array(
                        'observer_class' => get_class($observer)
                    ));
                    return false;
                }
            }
            
            $this->observers[] = $observer;
            
            $this->logger->debug('Observer attached', array(
                'observer_class' => get_class($observer),
                'total_observers' => count($this->observers)
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to attach observer', array(
                'error' => $e->getMessage(),
                'observer_class' => get_class($observer)
            ));
            return false;
        }
    }
    
    /**
     * Detach an observer
     * 
     * @param AANP_Observer_Interface $observer
     * @return bool
     */
    public function detach(AANP_Observer_Interface $observer) {
        try {
            foreach ($this->observers as $key => $existing_observer) {
                if ($existing_observer === $observer) {
                    unset($this->observers[$key]);
                    $this->observers = array_values($this->observers); // Re-index array
                    
                    $this->logger->debug('Observer detached', array(
                        'observer_class' => get_class($observer),
                        'remaining_observers' => count($this->observers)
                    ));
                    
                    return true;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to detach observer', array(
                'error' => $e->getMessage(),
                'observer_class' => get_class($observer)
            ));
            return false;
        }
    }
    
    /**
     * Notify all observers
     * 
     * @param string $event
     * @param array $data
     * @return array
     */
    public function notify($event, $data = array()) {
        $results = array();
        $start_time = microtime(true);
        
        try {
            $this->logger->debug('Notifying observers for event', array(
                'event' => $event,
                'observer_count' => count($this->observers),
                'data_keys' => array_keys($data)
            ));
            
            // Sort observers by priority (higher priority first)
            $sorted_observers = $this->get_sorted_observers();
            
            foreach ($sorted_observers as $observer) {
                try {
                    // Check if observer is interested in this event
                    if (!$observer->is_interested_in($event)) {
                        continue;
                    }
                    
                    $observer_start_time = microtime(true);
                    $response = $observer->update($event, $data, $this);
                    $observer_end_time = microtime(true);
                    
                    $results[] = array(
                        'observer' => get_class($observer),
                        'response' => $response,
                        'execution_time' => ($observer_end_time - $observer_start_time) * 1000 // ms
                    );
                    
                } catch (Exception $e) {
                    $this->logger->error('Observer notification failed', array(
                        'event' => $event,
                        'observer_class' => get_class($observer),
                        'error' => $e->getMessage()
                    ));
                    
                    $results[] = array(
                        'observer' => get_class($observer),
                        'error' => $e->getMessage(),
                        'execution_time' => 0
                    );
                }
            }
            
            $total_time = (microtime(true) - $start_time) * 1000; // ms
            
            $this->logger->debug('Observer notification completed', array(
                'event' => $event,
                'total_execution_time' => $total_time,
                'notified_observers' => count($results)
            ));
            
        } catch (Exception $e) {
            $this->logger->error('Observer notification system failed', array(
                'event' => $event,
                'error' => $e->getMessage()
            ));
        }
        
        return $results;
    }
    
    /**
     * Get attached observers sorted by priority
     * 
     * @return array
     */
    private function get_sorted_observers() {
        $sorted = $this->observers;
        usort($sorted, function($a, $b) {
            return $b->get_priority() - $a->get_priority();
        });
        return $sorted;
    }
    
    /**
     * Get attached observers
     * 
     * @return array
     */
    public function get_observers() {
        return $this->observers;
    }
}

/**
 * Base Observer Class
 */
abstract class AANP_Observer_Base implements AANP_Observer_Interface {
    
    /**
     * Observer priority
     * @var int
     */
    protected $priority = 10;
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    protected $logger;
    
    /**
     * Constructor
     */
    public function __construct($priority = 10) {
        $this->priority = $priority;
        $this->logger = AANP_Logger::getInstance();
    }
    
    /**
     * Update method (to be implemented by subclasses)
     * 
     * @param string $event
     * @param array $data
     * @param AANP_Subject_Interface $subject
     * @return mixed
     */
    abstract public function update($event, $data, AANP_Subject_Interface $subject);
    
    /**
     * Get priority
     * 
     * @return int
     */
    public function get_priority() {
        return $this->priority;
    }
    
    /**
     * Check if interested in event (default: all events)
     * 
     * @param string $event
     * @return bool
     */
    public function is_interested_in($event) {
        return true; // By default, interested in all events
    }
}

/**
 * Event Manager for Centralized Event Handling
 */
class AANP_Event_Manager {
    
    /**
     * Event subjects
     * @var array
     */
    private $subjects = array();
    
    /**
     * Logger instance
     * @var AANP_Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = AANP_Logger::getInstance();
    }
    
    /**
     * Get or create subject for event
     * 
     * @param string $event Event name
     * @return AANP_Subject_Interface
     */
    public function get_subject($event) {
        if (!isset($this->subjects[$event])) {
            $this->subjects[$event] = new AANP_Event_Subject();
        }
        
        return $this->subjects[$event];
    }
    
    /**
     * Subscribe observer to event
     * 
     * @param string $event Event name
     * @param AANP_Observer_Interface $observer
     * @return bool
     */
    public function subscribe($event, AANP_Observer_Interface $observer) {
        $subject = $this->get_subject($event);
        return $subject->attach($observer);
    }
    
    /**
     * Unsubscribe observer from event
     * 
     * @param string $event Event name
     * @param AANP_Observer_Interface $observer
     * @return bool
     */
    public function unsubscribe($event, AANP_Observer_Interface $observer) {
        if (!isset($this->subjects[$event])) {
            return false;
        }
        
        return $this->subjects[$event]->detach($observer);
    }
    
    /**
     * Trigger event
     * 
     * @param string $event Event name
     * @param array $data Event data
     * @return array Results from observers
     */
    public function trigger($event, $data = array()) {
        if (!isset($this->subjects[$event])) {
            $this->logger->warning('Triggering event with no observers', array('event' => $event));
            return array();
        }
        
        return $this->subjects[$event]->notify($event, $data);
    }
    
    /**
     * Get all events
     * 
     * @return array
     */
    public function get_events() {
        return array_keys($this->subjects);
    }
    
    /**
     * Get observers for event
     * 
     * @param string $event
     * @return array
     */
    public function get_observers($event) {
        if (!isset($this->subjects[$event])) {
            return array();
        }
        
        return $this->subjects[$event]->get_observers();
    }
}