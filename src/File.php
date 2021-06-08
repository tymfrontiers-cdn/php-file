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

  const STORAGE_DIR = FILE_STORAGE_DIR;
  protected static $_primary_key = 'id';
  protected static $_db_name;
  protected static $_table_name;
  protected static $_prop_type = [];
  protected static $_prop_size = [];
  protected static $_db_fields=[
    'id',
    '_locked',
    '_checksum',
    '_watermarked',
    'owner',
    'privacy',
    'caption',
    'nice_name',
    'type_group',
    '_path',
    '_name',
    '_size',
    '_type',
    '_creator',
    '_updated',
    '_created'
  ];

  public $id;
  private $_locked = false;
  private $_checksum = NULL;
  private $_watermarked = false;
	public $owner;
  public $privacy = 'PUBLIC';
	public $caption;
	public $nice_name;
	public $type_group;

  protected $_path;
	protected $_name;
	protected $_size;
	protected $_type;

	private $_creator;
	private $_updated;
	private $_created;

	public $errors = []; # follows Tym Error system

  // function __construct( $filename = "", bool $mkdir = false){
  //   self::_checkEnv();
  //   if( !empty($filename) ){
  //     $this->load($filename, $mkdir);
  //   }
  // }
  private static function _checkEnv(){
    if( !\defined('MYSQL_FILE_DB') || !\defined('MYSQL_FILE_TBL') ){
      throw new \Exception("File storage database not defined. Define constant 'MYSQL_FILE_DB' to hold name of database where file meta info will be stored.", 1);
    }
    if( ! \defined('MYSQL_FILE_TBL') ){
      throw new \Exception("File storage table not defined. Define constan 'MYSQL_FILE_TBL' to hold name of database table where file meta info will be stored.", 1);
    }
    self::$_db_name = MYSQL_FILE_DB;
    self::$_table_name = MYSQL_FILE_TBL;
  }
  public function load($filename, bool $mkdir = false){
    if ($mkdir && !\is_int($filename)) {
      if( !\file_exists($filename) ){
        \mkdir($filename,0777,true);
      }
    }
    if( \is_dir($filename) ){
      // init directory
      $this->_path = \str_replace(self::STORAGE_DIR, "", $filename);
    }elseif( \is_file($filename) ){
      // init file
      $file = \pathinfo($filename);
      if( !\array_key_exists($file['extension'],$this->mime_types) && !\in_array( \mime_content_type($filename),$this->mime_types ) ){
        throw new \Exception("Unknown file type given", 1);
      }
      $this->_path = \str_replace(self::STORAGE_DIR, "", $file['dirname']);
      $this->_name = $file['basename'];
      $this->_size = filesize($filename);
      $this->_type =  \array_key_exists($file['extension'],$this->mime_types) ?  $this->mime_types[$file['extension']] : \mime_content_type($filename);
      $this->type_group = $this->groupName();
      $this->nice_name = $file['filename'];
    }elseif( \is_int($filename) ){
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
  public function groupName( string $mime = "") {
    $mime = !empty($mime) ? $mime : $this->_type;
    return !\array_key_exists($mime, $this->mime_group)
      ? NULL
      : $this->mime_group[$mime];
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
    return "//" . (\defined("PRJ_FILE_DOMAIN") ? PRJ_FILE_DOMAIN : PRJ_DOMAIN) . "/app/file/{$this->_name}";
  }
	public function fullPath(){ return self::STORAGE_DIR . $this->_path."/".$this->_name; }
	public function create(){ return $this->_create();}
  public function destroy() {
    global $session;
    if ($this->_creator !== $session->name) {
      $this->errors['destroy'][] = [0,256,"Access denied",__FILE__,__LINE__];
      return false;
    }
    try {
      Helper\setting_unset_file_default($this->id);
      \unlink( $this->fullPath() );
      $this->delete();
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
    if ((bool)$this->_locked) {
      $this->errors['update'][] = [0,256, "File is locked and can not be updated",__FILE__, __LINE__];
      return false;
    }
    return !empty($this->id) ? $this->_update() : false;
  }
  public function checksum() { return $this->_checksum; }
  public function locked() { return $this->_locked; }
  public function watermarked() { return $this->_watermarked; }
  public function creator() { return $this->_creator; }
  public function lock() {
    // calculate and save checksum
    $this->_checksum = \hash_file("sha512", $this->fullPath(), false);
    $this->_locked = true;
    return $this->_update();
  }

}
