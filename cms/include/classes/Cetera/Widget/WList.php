<?php
/**
 * Cetera CMS 3 
 *
 * @package CeteraCMS
 * @version $Id$
 * @copyright 2000-2010 Cetera labs (http://www.cetera.ru) 
 * @author Roman Romanov <nicodim@mail.ru> 
 **/
namespace Cetera\Widget; 

/**
 * Виджет "Список материалов"
 * 
 * @package CeteraCMS
 */ 
class WList extends Templateable { 

	use Traits\Catalog;
	use Traits\Paginator;
	
	public static $name = 'List';
	
	protected $_children = null;
 	
	protected function initParams()
	{
		$this->_params = array(
			'name'               => '',
			'catalog'            => 0,
			'where'              => null,
			'limit'              => 10,
			'page'               => null,
			'page_param'         => 'page',
			'order'              => 'dat',
			'sort'               => 'DESC',
			'catalog_link'       => null,
			'iterator'           => null,
			'paginator'          => false,
			'paginator_url'      => '?{query_string}',
			'paginator_template' => false,
			'ajax'               => false,
			'infinite'           => false,
			'css_class'          => 'content',
			'css_row'            => '',
			'template'		     => 'default.twig',
		); 
		
	}

	protected function init()
	{
		if ($this->_params['ajax'] && $this->_params['infinite'] && !$this->_params['paginator_template']) {
			$this->_params['paginator_template'] = 'infinite.twig';
		}
	}
    
	/**
	 * Список материалов для показа
	 */ 	
    public function getChildren()
    {
		if (!$this->_children)
		{
			if ($this->getParam('iterator')) {
				$this->_children = $this->getParam('iterator');
			}
			else {
				$this->_children = $this->getCatalog()->getMaterials()->orderBy($this->getParam('order'), $this->getParam('sort'));				
				if ($this->getParam('subfolders') || $this->getParam('subsections')) {
					$this->_children->subfolders();
				}
			}
			if ($this->getParam('limit')) $this->_children->setItemCountPerPage($this->getParam('limit')); 
			if ($this->getParam('where')) $this->_children->where($this->getParam('where')); 
			$this->_children->setCurrentPageNumber( $this->getPage() ); 			
		}
		return $this->_children;
    }  	
}