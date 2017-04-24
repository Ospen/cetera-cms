<?php
namespace Cetera; 

/**
 * Cetera CMS 3 
 *
 * @package CeteraCMS
 * @version $Id$
 * @author Roman Romanov <nicodim@mail.ru> 
 **/
 
/**
 * Тема
 *
 * @package CeteraCMS
 **/ 
class Theme implements \ArrayAccess  {

    private $_info = null;
    
    public $name;
    public $config;
    
    public static function enum()
    {
        $data = array();
        if (file_exists(DOCROOT.THEME_DIR) && is_dir(DOCROOT.THEME_DIR) && $__dir = opendir(DOCROOT.THEME_DIR)) {
        	while (($__item = readdir($__dir)) !== false)
          {
          		if($__item=="." or $__item==".." or !is_dir(DOCROOT.THEME_DIR.'/'.$__item)) continue;
        	    if (!file_exists(DOCROOT.THEME_DIR.'/'.$__item.'/'.THEME_INFO)) continue;
              $data[$__item] = new self($__item);
        	}  
        	closedir($__dir);
        }    
        return $data;
    }
    
    public static function install($theme, $status = null, $translator = null, $schema = null, $extract_initial_data = false)
    {
        if (!$translator) $translator = new TranslateDummy();
		
		// предыдущая версия темы
		$previousTheme = self::find($theme);
		if ($previousTheme) $previousTheme->grabInfo();
    
        $themePath = WWWROOT.THEME_DIR.'/'.$theme;
        $archiveFile = WWWROOT.THEME_DIR.'/'.$theme.'.zip';
                
        // Загрузка
        if ($status) $status($translator->_('Загрузка темы'), true);
        
        if (!file_exists(WWWROOT.THEME_DIR)) mkdir(WWWROOT.THEME_DIR);
      
        if (!file_exists(WWWROOT.THEME_DIR)) 
            throw new \Exception($translator->_('Каталог').' '.DOCROOT.THEME_DIR.' '.$translator->_('не существует'));      
        
        if (!is_writable(WWWROOT.THEME_DIR)) 
            throw new \Exception($translator->_('Каталог').' '.DOCROOT.THEME_DIR.' '.$translator->_('недоступен для записи'));

    	$client = new \GuzzleHttp\Client();
    	$res = $client->get(THEMES_INFO.'?download='.$theme, ['verify' => false]);
    	$d = $res->getBody();   		
		
        if (!$d) throw new \Exception('Не удалось скачать тему');
        file_put_contents($archiveFile, $d);  
        
        if ($status) $status('OK', false);                
        
		/* не удаляем предыдущую версию, чтобы сохранить пользовательские файлы
        if (file_exists($themePath)) {
            // Удаление предыдущей версии
            if ($status) $status($translator->_('Удаление предыдущей версии'), true);
            Util::delTree($themePath);
            if ($status) $status('OK', false);  
        }
        */
		
        // Распаковка архива  
        if ($status) $status($translator->_('Распаковка архива'), true);      
        $zip = new \ZipArchive;
        if($zip->open($archiveFile) === TRUE)
		{ 
        	if(!$zip->extractTo(WWWROOT.THEME_DIR)) throw new Exception('Не удалось распаковать архив '.$archiveFile);                     
        	$zip->close(); 
            unlink($archiveFile); 
			
            if (file_exists(DOCROOT.THEME_DIR.'/'.$theme.'/'.PLUGIN_CONFIG))
        		    include(DOCROOT.THEME_DIR.'/'.$theme.'/'.PLUGIN_CONFIG);  			
              
        } else throw new \Exception('Не удалось открыть архив '.$archiveFile); 
        if ($status) $status('OK', false);  
                  
        // Модификация БД   		
        if ($status) $status($translator->_('Модификация БД'), true);       
        $schema = new Schema(); 
        $schema->fixSchema('theme_'.$theme);  
        if ($status) $status('OK', false);  
		
		$t = new self($theme);
		Plugin::installRequirements($t->requires, $status, $translator);		
		
		if ($extract_initial_data && file_exists($themePath.'/'.THEME_DB_DATA))
		{
			if ($status) $status($translator->_('Начальная структура сайта'), true);      
			$schema->readDumpFile($themePath.'/'.THEME_DB_DATA);
			if ($status) $status('OK', false);  	
		}
		
		// В файле config.json конфингурация темы "по умолчанию". Устанавливаем её для всех серверов
		if (file_exists($themePath.'/config.json')) {
			$config = json_decode( file_get_contents($themePath.'/config.json'), true );
			foreach (Server::enum() as $s) {				
				$t->loadConfig( $s );

				if (!$t->config) {
					$res = $config;
				} 
				else {
					$current = get_object_vars($t->config);   
					$res = array_merge( $config, $current );
				}					
				$t->setConfig( $res, $s );				
			}			
		}

		//
        if (file_exists($themePath.'/'.THEME_INSTALL))
		{
			$currentTheme = self::find($theme);
            include $themePath.'/'.THEME_INSTALL;
		}
    }    
    
    public static function find($name)
    {
        if (!is_dir(DOCROOT.THEME_DIR.'/'.$name)) return false;
        if (!file_exists(DOCROOT.THEME_DIR.'/'.$name.'/'.THEME_INFO)) return false;
        return new self($name);
    }    
    
    function __construct($name)
    {
        $this->name = $name;
    }
	
    public function getPath()
    {   
		return WWWROOT.THEME_DIR.'/'.$this->name;
	}
	
    public function getUrl()
    {   
		return '/'.THEME_DIR.'/'.$this->name;
	}	
	
	public function addTranslation($translator)
	{
		if (!file_exists( $this->getPath().'/'.TRANSLATIONS )) return;
		$translator->addTranslation( $this->getPath().'/'.TRANSLATIONS );
	}
    
    public function delete($data = false)
    {     
        Util::delTree( $this->getPath() );
        foreach (Server::enum() as $s) {
            $tname = str_replace( THEME_DIR.'/', '', $s->templateDir );
            if ($tname == $this->name) {
                $s->setTheme();
            }
        }
    }
    
    public function loadConfig(Server $server, $name = false)
    {
		if (!$name) $name = $this->name;
        $c = $server->getDbConnection()->fetchAssoc('SELECT config FROM theme_config WHERE theme_name = ? and server_id = ?', array($name, $server->id));
        if ($c) $this->config = json_decode($c['config']); else $this->config = null;
        return $this;
    } 
    
    public function setConfig($config, Server $server)
    {
        
        $conn = $server->getDbConnection();
        
        $conn->delete('theme_config', array(
            'theme_name'  => $this->name,
            'server_id' => $server->id
        ));
        
        $conn->insert('theme_config', array(
            'theme_name'  => $this->name,
            'server_id' => $server->id,
            'config'    => json_encode($config)
        ));         
        
        return $this;
    }               
    
    public function offsetExists ( $offset )
    {
     
        $this->grabInfo();
        return array_key_exists ( $offset , $this->_info );
    
    }
    
    public function offsetGet ( $offset )
    {
    
        $this->grabInfo();
        return isset($this->_info[ $offset ])?$this->_info[ $offset ]:null;
    
    }
    
    public function offsetSet ( $offset , $value ) {
        $this->_info[ $offset ] = $value;
    }
    
    public function offsetUnset ( $offset ) {} 
    
    public function __get($name)
    {
    
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) return $this->$method();
        
        $this->grabInfo();
        if ($this->offsetExists ( $name )) return $this->_info[$name];
    
        return null;
    }  

	private function loadInfo()
	{
        if (file_exists(DOCROOT.THEME_DIR.'/'.$this->name.'/'.THEME_INFO)) {
        
            $info = json_decode(implode('',file(DOCROOT.THEME_DIR.'/'.$this->name.'/'.THEME_INFO)), true);
            
        }
		else {
            $info = array(
                  'title'       => $this->name,
                  'description' => ''
            );
        } 
		return $info;
	}
	
	private function saveInfo($info)
	{
		file_put_contents(DOCROOT.THEME_DIR.'/'.$this->name.'/'.THEME_INFO, json_encode($info));
	}	
    
    private function grabInfo()
    {
        if (is_array($this->_info)) return;
        
		$this->_info = $this->loadInfo();
		$this->_info['id'] = $this->name;
        
        if (!isset($this->_info['schema'])) {
            if (file_exists(DOCROOT.THEME_DIR.'/'.$this->name.'/'.THEME_DB_SCHEMA)) 
                $this->_info['schema'] = DOCROOT.THEME_DIR.'/'.$this->name.'/'.THEME_DB_SCHEMA;   
        } else {
                $this->_info['schema'] = DOCROOT.THEME_DIR.'/'.$this->name.'/'.$this->_info['schema'];
        }   
    } 

	public function dumpData()
	{
		if (count(Server::enum()) != 1) {
			throw new \Exception('Должен быть только 1 сервер');
		}
		$s = Server::getDefault();
		if ($s->getTheme()->name != $this->name) {
			throw new \Exception('Для сервера установлена другая тема');
		}
		
		// сохраняем конфигурацию темы
		$this->loadConfig($s);
		file_put_contents($this->getPath().'/config.json', json_encode($this->config) );
		
		// сохраняем конфигурацию schema.xml
		$f = fopen($this->getPath().'/'.THEME_DB_SCHEMA, 'w');
		fwrite($f, "<"."?xml version=\"1.0\"?".">\n");
		fwrite($f, "<schema>\n\n");
		foreach (Widget\Container::enum() as $w) {
			fwrite($f, $w->getXml()."\n");
		}
		fwrite($f, "\n</schema>");
		fclose($f);
		
		// сохраняем начальный контент
		$a = Application::getInstance();
		
		$tables = array(
			'dir_data', 'dir_structure', 'menus', 'types', 'types_fields'
		);
		
		$types = $a->getDbConnection()->fetchAll('SELECT * FROM types WHERE alias NOT IN ("users","dir_data")');
		foreach ($types as $t) $tables[] = $t['alias'];
		
		$settings = array(
			'include-tables' => array_unique($tables),
            'add-drop-table' => true,
            'single-transaction' => false,
            'lock-tables' => false,
            'add-locks' => false,		
			'extended-insert' => false,
			'no-autocommit' => false,
		);
		
		$dump = new \Ifsnop\Mysqldump\Mysqldump(
			'mysql:host='.$a->getVar('dbhost').';dbname='.$a->getVar('dbname'),
			$a->getVar('dbuser'),
			$a->getVar('dbpass'),
			$settings
		);
		$dump->start($this->getPath().'/'.THEME_DB_DATA);
	}
	
	public function rename($name)
	{
		if ($name == $this->name) {
			throw new \Exception('Нельзя переименовать в то же имя');
		}
				
		$a = Application::getInstance();		
		
		$oldname = $this->name;
		$this->name = $name;
				
		$types = $a->getDbConnection()->fetchAll('SELECT * FROM types');
		foreach ($types as $t) {
			$od = ObjectDefinition::findById($t['id']);
			$fields = array();
			foreach ($od->getFields() as $f) {
				if (in_array($f['type'], array(FIELD_FILE,FIELD_LONGTEXT,FIELD_TEXT,FIELD_HUGETEXT))) {
					$fields[] = $f->name;
				}
			}
			foreach ($od->getMaterials() as $m) {
				$count = 0;
				foreach ($fields as $f) {
					$m->fields[$f] = str_replace(THEME_DIR.'/'.$oldname.'/', THEME_DIR.'/'.$this->name.'/', $m->fields[$f], $c);
					$count = $count + $c;
				}
				if ($count) $m->save();
			}
		}	
		
		foreach (Server::enum() as $s) {
			$t = $s->getTheme();
			if (!$t) continue;
			if ($t->name != $oldname) continue;
			
			// обновим установку для сервера
			$s->setTheme($this);
			
			// обработка конфига
			$this->loadConfig($s,$oldname);
			$config = array();
			foreach ($this->config as $key => $value) {
				$config[$key] = str_replace(THEME_DIR.'/'.$oldname.'/', THEME_DIR.'/'.$this->name.'/', $value);
			}
			$this->setConfig($config, $s);
		}
		
		// обработка виджетов
		$widgets = $a->getDbConnection()->fetchAll('SELECT * FROM widgets');
		foreach ($widgets as $w) {
			$p = unserialize($w['params']);
			if (isset($p['template'])) {
				$p['template'] = str_replace(THEME_DIR.'/'.$oldname.'/', THEME_DIR.'/'.$this->name.'/', $p['template']);
				$w['params'] = serialize($p);
				$a->getDbConnection()->update('widgets',$w,array('id'=>$w['id']));
			}
		}
				
		// переименуем каталог
		rename(WWWROOT.THEME_DIR.'/'.$oldname, $this->getPath());
		
		// Переименование Ext компонента редактирования настроек
		$text = file_get_contents($this->getPath().'/ext/Config.js');
		$text = str_replace("'Theme.".$oldname.".Config'","'Theme.".$this->name.".Config'",$text);
		file_put_contents($this->getPath().'/ext/Config.js', $text);	
	}	

	public function isDeveloperMode()
	{
		return file_exists( $this->getPath().'/.developer_mode' );
	}	

	public function isDisableUpgrade()
	{
		if (isset($this->_info['disableUpgrade'])) return (boolean)$this->_info['disableUpgrade'];
		return file_exists( $this->getPath().'/.disable_upgrade' );
	}	
	
	public function setDeveloperMode($value)
	{
		if ($value)
			$this->setFile('.developer_mode');
			else $this->unlinkFile('.developer_mode');		
	}	
	
	public function setDisableUpgrade($value)
	{
		if ($value)
			$this->setFile('.disable_upgrade');
			else $this->unlinkFile('.disable_upgrade');
	}	
	
	private function setFile($name)
	{
		file_put_contents( $this->getPath().'/'.$name, 'TRUE' );
	}
	
	private function unlinkFile($name)
	{
		if (file_exists( $this->getPath().'/'.$name )) unlink( $this->getPath().'/'.$name );
	}	
	
	public function update($data)
	{
		$info = $this->loadInfo();
		
		if (isset($data['disableUpgrade'])) {
			$this->setDisableUpgrade( $data['disableUpgrade'] );
			unset($data['disableUpgrade']);
		}
		
		if (isset($data['developerMode'])) {
			$this->setDeveloperMode( $data['developerMode'] );
			unset($data['developerMode']);
		}	

		if (isset($info['disableUpgrade'])) unset($info['disableUpgrade']);
		
		foreach($info as $key => $value) {
			if (isset($data[$key])) {
				$info[$key] = $data[$key];
			}
		}
		$this->saveInfo($info);
		
		if ($data['name'] && $data['name'] != $this->name) {
			$this->rename($data['name']);
		}	
		return $this;
		
	}

	public function uploadToMarket($key)
	{
		$client = new \GuzzleHttp\Client();
		
    	$res = $client->get(THEMES_INFO.'?upload_request=1&theme='.$this->name.'&developer_key='.$key.'&version='.$this->version, ['verify'=>false]);
    	$d = json_decode($res->getBody(), true); 
		if (!$d['success']) {
			throw new \Exception( $d['message'] );
		}
		
		$this->dumpData();
		
		$zipFile = CACHE_DIR.'/'.$this->name.'.zip';
		if (file_exists($zipFile)) unlink($zipFile);
		HZip::zipDir($this->getPath(), $zipFile , array('.git','.disable_upgrade','.developer_mode') );
		
		$f = fopen($zipFile, 'r');
    	$res = $client->request('POST',THEMES_INFO.'?upload=1&theme='.$this->name.'&developer_key='.$key.'&version='.$this->version, ['verify'=>false, 'body' => $f]);
		unlink($zipFile);
    	$d = json_decode($res->getBody(), true); 
		if (!$d['success']) {
			throw new \Exception( $d['message'] );
		}		
		
		return $this;
			
	}

}