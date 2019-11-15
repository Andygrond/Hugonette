<?php

namespace Andygrond\Hugonette;

/* Simple fast routing for Hugo websites
 * @author Andygrond 2019
**/

class Route
{
	private $view = null;		// view object
	private $viewClass;		// view class name
	private $requestPath;	// path of file requested
	private $requestFile;		// file requested
	
	private $cfg = [		// configuration data
		'requestBase' => HOME_URI,
		'publishBase' => HUGO_DIR,
	];


	public function __construct()
	{
		$req = explode('?', urldecode($_SERVER['REQUEST_URI']));
		if (substr($req[0], -5) == '.html') {
			$pi = pathinfo($req[0]);
			$file = $pi['basename'];
			$path = $pi['dirname'];
			
		} else {
			$file = 'index.html';
			$path = $req[0];
		}

		if (strpos($path, $this->cfg['requestBase']) === 0) {
			$path = substr($path, strlen($this->cfg['requestBase']));
		}

		$this->viewMode('plain');
		$this->requestFile = $file;
		$this->requestPath = trim($path, '/');
	}
	
	// shutdown handler
	public function __destruct()
	{
		if ($this->view === null) {	// page has not been found till now
			ErrorView::status(404);
		}
		Log::close();
	}

	// set the mode of view for the subsequent routes
	// view modes: plain - latte - json
	public function viewMode($mode)
	{
		$this->viewClass = __NAMESPACE__ .'\\' .ucwords($mode) .'View';
	}
	
	// route for single request method
	// @method - any http method expected as a function name
	// @args = [$pattern, $model, $template]
	public function __call($method, $args)
    {
		if ($this->checkMethod($method)) {
			if ($params = $this->matchPattern($args[0])) {
				$template = $args[2]?? $this->template();
				$this->render($template, $args[1]);
			}
		}
    }

	// full static GET with one common model
	public function hugo($model)
    {
		if ($this->checkMethod('GET')) {
			if ($template = $this->template()) {
				$this->render($template, $model);
			}
		}
    }

	// redirect $to if URI starts from $pattern
	// @$permanent defaults to 302 http code
	public function redirect($pattern, $to, $permanent = true)
    {
		if ($this->startPattern($pattern)) {
			$code = $permanent? 301 : 302;
			$this->view = ErrorView::redirect($code, $to);
			exit;
		}
	}
	
	// HTTP status code & error page response if URI starts from $pattern
/*/ perhaps not needed anymore...
	public function error($pattern, $code)
    {
		if ($this->startPattern($pattern)) {
			$this->view = ErrorView::status($code);
			exit;
		}
	}
*/

// ==================
	// checking http request method
	private function checkMethod($method)
	{
		return !strcasecmp($_SERVER['REQUEST_METHOD'], $method);
	}

	// check pattern matching
	private function matchPattern($pattern)
	{
		$pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';
		if (preg_match($pattern, '/' .$this->requestPath, $params) === 1) {
			bdump($params, 'params');
			return $params;
		}
		return false;
	}
	
	// check pattern starting from
	private function startPattern($pattern)
	{
		return (strpos($this->requestPath, $pattern) === 0);
	}

	// get template file name if exist
	private function template()
	{
		$path = $this->requestPath? $this->requestPath .'/' : '';
		$template = $this->cfg['publishBase'] .$path .$this->requestFile;
		return is_file($template)? $template : null;
	}

	private function render($template, $model)
	{
		$this->view = new $this->viewClass($template);
		$this->view->render($model);
		exit;
	}
	
}
