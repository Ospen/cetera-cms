<?php
/**
 * Cetera CMS 3 
 *
 * @package CeteraCMS
 * @version $Id$
 * @author Roman Romanov <nicodim@mail.ru> 
 **/
 
namespace Cetera;
 
/**
 * Базовый класс для объектов системы
 *
 * @property int $id идентификатор объекта
 * 
 * @package CeteraCMS
 */ 
abstract class Base {
   
    /** @internal */     
   protected $_id;
   
	/**
	 * Плагины
	 * @internal
	 */  
    public static $plugins = array();  
	private $pluginInstances = array(); 
	public static $extensions = array(); 
   
    /**
     * Конструктор    
     *  
     * @internal	 
     * @param array поля объекта
     * @return void     
     */    
    protected function __construct($fields = null) 
    {
    	  if (is_array($fields))
            $this->setFields($fields);
    }
     
    /**
     * Устанавливает свойства объекта   
     * 
     * @internal     
     * @param array значения свойств объекта
     * @return void     
     */   	 
    protected function setFields($fields) 
    {
    	foreach ($fields as $name => $value) {
            $property = '_' . $name;
            if (property_exists($this, $property)) $this->$property = $value;
        }
    }    
    
    /**
	 * @internal
     * Конвертирует в json-строку {'id':ID_объекта}     
     */   		
    public function __toString()
    {
        return json_encode(array('id' => $this->id));
    }    
	
    /**
     * Возвращает объект в виде массива с указанными полями
     *            
     * @return array 
     */ 	
    public function asArray()
    {
		if (func_num_args() == 0) {
			$fields = array('id');
		}
		else {
			$fields = array();
			$args = func_get_args();
			if (count($args)==1 && is_array($args[0])) {
				$args = $args[0];
			}			
			foreach ($args as $f ) {
				if (is_string($f)) $fields[] = $f;
			}
		}
		
		$obj = array();
		foreach ($fields as $f) {
			$obj[$f] = $this->$f;
		}
		return $obj;
    }
         
    /**
     * Перегрузка чтения свойств класса. 
     *           
     * Если в классе существует метод getСвойство(), то вызывается этот метод
     * Если в классе существует поле $_свойство, то возвращается это поле
     * В противном случает бросается исключение         
     *    
	 * @internal
     * @param string $name свойство класса          
     * @return mixed
     * @throws LogicException          
     */          
    public function __get($name)
    {
    
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) return $this->$method();
        
        $property = '_' . $name;
        if (property_exists($this, $property)) return $this->$property;
    
        throw new \LogicException("Property {$name} is not found");
    }

    /**
     * Перегрузка записи свойств класса. 
     *   
     * Если в классе существует метод setСвойство(), то вызывается этот метод
     * Если в классе существует поле $свойство, то полю присваивается значение свойства
     * В противном случает бросается исключение   
     *     
     * @internal	 
     * @param string $name свойство класса   
     * @param mixed $value значение свойства           
     * @return void
     * @throws LogicException          
     */ 
    public function __set($name, $value)
    {
    
        $method = 'set' . ucfirst($name);
        if (method_exists($this, $method)) return $this->$method($value);
                                 
        if (property_exists($this, $name)) {
            $this->$name = $value;
            return;
        }
    
        throw new \LogicException("Property {$name} is not found");
    }
     
    /**
     * Disallow cloning 
     *         
     * @ignore
     */         
    final private function __clone(){}
	
    /**
     * Расширяет функциональность класса с помощью методов другого класса.
     *     
     * @internal	 
     * @param string $name свойство класса   
     * @param mixed $value значение свойства           
     * @return void
     * @throws LogicException          
     */ 	
	public static function addPlugin( $class )
	{
		if (is_subclass_of($class, '\Cetera\ObjectPlugin'))
		{
			static::$plugins[] = $class;
		} 
		else 
		{
		    throw new \LogicException("{$class} must extend \\Cetera\\ObjectPlugin");
		}
	}
	
	public static function extend( $class )
	{
		self::$extensions[ get_called_class() ] = $class;
	}
	
	protected static function create()
	{
		if ( isset(self::$extensions[ get_called_class() ]) && self::$extensions[ get_called_class() ] )
		{
			return new self::$extensions[ get_called_class() ];
		}
		return new static();
	}
		
    public function __call($name, $arguments)
	{
		foreach ( static::$plugins as $plugin )
		{
			if ( method_exists ( $plugin , $name ) )
			{
				if (!isset( $this->pluginInstances[ $plugin ] ))
				{
					$this->pluginInstances[ $plugin ] = new $plugin( $this );
				}
				return call_user_func_array ( array( $this->pluginInstances[ $plugin ], $name ) , $arguments );
			}
		}
		if (!count($arguments))try
		{
			return static::__get( $name );
		}
		catch (\LogicException $e)
		{
			throw new \LogicException("Method {$name} is not exists");
		}
		throw new \LogicException("Method {$name} is not exists");
    }
	
    
}
