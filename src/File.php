<?php
namespace TymFrontiers;

class File{
  use Helper\MySQLDatabaseObject,
      Helper\Pagination,
      Helper\FileData,
      Helper\FileUploader,
      Helper\FileDownloader,
      Helper\PhotoEditor,
      Helper\FileWriter,
      Helper\FileReader{
    		Helper\FileUploader::save insteadof Helper\MySQLDatabaseObject;
    	}

  protected static $_primary_key='id';
  protected static $_db_name = MYSQL_BASE_DB;
  protected static $_table_name='file';
  protected static $_prop_type = [];
  protected static $_prop_size = [];
  protected static $_db_fields=[
    'id',
    'owner',
    'privacy',
    'caption',
    'nice_name',
    '_path',
    '_name',
    '_size',
    '_type',
    '_updated',
    '_created'
  ];

  public $id;
	public $owner;
  public $privacy = 'PUBLIC';
	public $caption;
	public $nice_name;

  protected $_path;
	protected $_name;
	protected $_size;
	protected $_type;

	private $_updated;
	private $_created;

	public $errors = []; # follows Tym Error system

  function __construct(){
    $this->_checkEnv();
    // if( !empty($filename) ){
    //   $this->load($filename,$mkdir);
    // }
  }
  private function _checkEnv(){
    // if( ! \defined('FILE_DB') ){
    //   throw new \Exception("File storage database not defined. Define constance 'FILE_DB' to hold name of database where file meta info will be stored.", 1);
    // }
    // if( ! \defined('FILE_TBL') ){
    //   throw new \Exception("File storage table not defined. Define constance 'FILE_TBL' to hold name of database table where file meta info will be stored.", 1);
    // }
    // $this->setDatabase( \FILE_DB );
    // $this->setTable( \FILE_TBL );
  }
  public function load($filename){
    if( !\is_int($filename) ){
      if( \is_dir($filename) && !\file_exists($filename) ){
        \mkdir($filename,0777,true);
      }
    }
    if( \is_dir($filename) ){
      // init directory
      $this->_path = $filename;
    }elseif( \is_file($filename) ){
      // init file
      $file = \pathinfo($filename);
      if( !\array_key_exists($file['extension'],$this->mime_types) && !\in_array( \mime_content_type($filename),$this->mime_types ) ){
        throw new \Exception("Unknown file type given", 1);
      }
      $this->_path = $file['dirname'];
      $this->_name = $file['basename'];
      $this->_size = filesize($filename);
      $this->_type =  \array_key_exists($file['extension'],$this->mime_types) ?  $this->mime_types[$file['extension']] : \mime_content_type($filename);
      $this->nice_name = $file['filename'];
    }elseif( \is_int($filename) ){
      global $db;
      $file = self::findById( (int)$filename );
      if( !$file ){
        throw new \Exception("No file was found with given ID: [{$filename}]", 1);
      }
      foreach ($file as $key => $value) {
        $this->$key = $value;
      }
    }elseif( \filter_var($filename,FILTER_VALIDATE_URL) ){
      throw new \Exception("Loading file via URL is not yet supported", 1);
      // download file
      //
    }else{
      throw new \Exception("File/path: '{$filename}' is invalid or does not exist", 1);
    }
  }
  public function type($ext=''){
    return !empty($ext) && \array_key_exists(strtolower($ext),$this->mime_types) ? $this->mime_types[strtolower($ext)] : (
      !empty($this->_type) ? $this->_type : 'unknown/unknown'
      );
  }
	public function mimeType(){ return $this->_type; }
	public function name(string $name=''){
    if( $name ) $this->_name = $name;
    return $this->_name;
  }
	public function path(string $path=''){
    return $this->_path;
  }
	public function size(int $size=0){
    return $this->_size;
  }
	public function url(){
    global $_SERVER;
    return "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}/file/{$this->_name}";
  }
	public function thumbUrl(){
    global $_SERVER;
    return "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}/file/thumb-{$this->_name}";
  }
	public function fullPath(){ return $this->_path."/".$this->_name; }
	public function create(){ return $this->_create();}
  public function destroy() {
    try {
      $this->delete();
      \unlink( $this->fullPath() );
    } catch ( \Exception $e) {
      $this->errors['destroy'][] = [0,256,"Failed to delete/destroy file, due to error: {$e->getMessage()}",__FILE__,__LINE__];
      return false;
    }
    return true;
  }
  // public function delete(){ return $this->destroy(); }
  public function sizeAsText() {
    if($this->size < 1024) {
      return "{$this->size} bytes";
    } elseif($this->size < 1048576) {
      $size_kb = \round($this->size/1024);
      return "{$size_kb} KB";
    } else {
      $size_mb = \round($this->size/1048576, 1);
      return "{$size_mb} MB";
    }
  }
  public function setDatabase(string $db){
    static::$_db_name = \preg_replace('/[^\w\-\_]+/', '', \str_replace(' ','_',$db) );
  }
  public function setTable(string $tbl){
    static::$_table_name = \preg_replace('/[^\w\-\_]+/', '', \str_replace(' ','_',$tbl) );
  }
  public function dbname(){ return static::$_db_name; }
  public function tblname(){ return static::$_table_name; }
  public function update(){
    return !empty($this->id) ? $this->_update() : false;
  }

}
