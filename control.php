<?php
require_once __DIR__."/exception.php";
interface EntityControler{
    public function list(Pagination $pagination = null);
    public function push($entity);
    public function get(string $id);
    public function delete(string $id);
    public function update(string $id,$entity);
}
abstract class BaseEntityControler{
    public $id;
    public $entity;
    public $pagination;
    public $sorter;
    public $filter;
    public $data;
    public $idss;
    function __construct(BaseStdArray $entity)
    {
        $this->entity = $entity;
        $this->pagination = new Pagination();
    }
    public function list(Pagination $pagination = null){
        $data = (array)clone $this->entity;
        foreach ($data as $key => $value) {
            if(!is_arrays($value) || empty($value)) unset($data[$key]);
        }
        if($this->filter != null) $data = $this->filter->result($data);
        if($this->sorter != null) $this->sorter->result($data);
        if($pagination != null) $pagination->result($data);
        foreach ($data as &$value) {
            run_event($this->id.'.read',$value);
        }
        return $data;
    }
    public function push($entity){
        if(empty($entity["id"])){
            $id = $this->makeid();
            $entity["id"] = $id;
        }
        Validation::$mode = "high";
        run_event($this->id.'.before_push',$entity);
        $this->entity[$entity["id"]] = $entity;
        Validation::$mode = "low";
        run_event($this->id.'.push',$entity);
        return true;
    }
    public function makeid(){
        if(isset($this->idss)){
            $id = $this->idss[$this->id];
            if(!$id){
                $id = arrs([
                    'id' => $this->id,
                    'current' => 0
                ]);
                $this->idss[$this->id] = $id;
            }
            $current = $id['current'];
            if(is_int($current)){
                $id['current'] = $current + 1;
                return $current;
            }
        }
        return uniqid();
    }
    public function get(string $id){
        return $this->entity[$id];
    }
    public function delete(string $id){
        if(isset($this->entity[$id])){
            run_event($this->id.'.delete',$this->entity[$id]);
            unset($this->entity[$id]); 
        }
    }
    public function update(string $id,$entity){
        $entity['id'] = $id;
        run_event($this->id.'.update',$entity);
        $root = $this->entity[$id];
        if(isset($root)){
            $root->merge($entity);
        }else{
            $this->entity[$id] = $entity;
        }
        $this->entity[$id]->setModifies([$id=>1]);
        return $this->entity[$id];
    }
}
class EntityHttpControl extends BaseEntityControler{
    public $method;
    public $meta_entity;
    public $validation;
    public $readonly = false;
    function __construct(BaseStdArray $entity){
        parent::__construct($entity);
        $this->filter = new Filter();
    }
    public function process(){
        $method = $_SERVER['REQUEST_METHOD'];
        if(isset($_GET['method'])){
            switch ($_GET['method']) {
                case 'new':
                    $this->method = "new";
                    break;
                case 'all':
                    $this->method = "all";
                    break;
                case 'delete':
                    $this->method = "delete";
                    break;
                default:
                    break;
            }
        }
        switch ($method) {
            case "GET":
                if(isset($_GET['id'])){
                    if($this->method == "delete"){
                        $this->todelete();
                        return;
                    }
                    $this->data = parent::get($_GET['id']);
                    $this->method = "get";
                }else{
                    if($this->method == "delete"){
                        $this->autodelete();
                        return;
                    }
                    if($this->method == "all") return;
                    if($this->method == "new") return;
                    if(isset($_GET['index'])){
                        $this->pagination->index = $_GET['index'];
                    }
                    if(isset($_GET['size'])){
                        $this->pagination->size = $_GET['size'];
                    }
                    $data = parent::list($this->pagination);
                    $this->data = $data;
                    $this->method = "list";
                }
                break;
            case "POST":
                if($this->readonly) throw new DeniedException("readonly",1);
                $input = arrs($_POST);
                isset($this->validation) && $this->validation->validate($input);
                if(isset($_GET['id'])){
                    $this->data = parent::update($_GET['id'],$input);
                    header("Location: " . $_SERVER["REQUEST_URI"]);
                    exit;
                }else{
                    if($this->method == "all"){
                        $data = $input;
                        foreach ($data as $key => $value) {
                            $this->meta_entity[$key] = $value;
                        }
                        unset($data['index']);
                        unset($data['data']);
                        $this->all($data,$input['index'],$input['data']);
                        header("Location: " . $_SERVER["SCRIPT_NAME"]);
                        exit;
                        return;
                    }
                    $input['created'] = new \DateTime();
                    $this->data = parent::push($input);
                    header("Location: " . $_SERVER["SCRIPT_NAME"]);
                    exit;
                }
                break;
            case "DELETE":
                if($this->readonly) throw new DeniedException("readonly",1);
                $this->todelete();
                break;
            default:
        }
    }
    public function all($meta,$index,$data){
        $is = explode("|",$index);
        $result = [];
        foreach(preg_split("/((\r?\n)|(\r\n?))/",$data) as $line){
            $lines = explode("|",$line);
            $data = [];
            foreach ($is as $key => $value) {
                if(isset($lines[$key]))
                $data[$value] = $lines[$key];
            }
            $result[] = $data;
        }
        foreach ($result as $key => &$value) {
            foreach ($meta as $k => $v) {
                if(!isset($value[$k])){
                    $value[$k] = $v;
                }
            }
            $value['created'] = new \DateTime(); 
        }
        foreach ($result as $key => $value) {
            try {
                parent::push($value);
            } catch (\Exception $ex) {
                //throw $ex;
            }
        }
    }
    public function todelete(){
        if(isset($_GET['id'])){
            $this->data = parent::delete($_GET['id']);
            header("Location: " . $_SERVER["SCRIPT_NAME"]);
        }
    }
    public function autodelete(){
        $pr = $_GET['pr'];
        $pr = explode(",",$pr);
        foreach ($pr as $value) {
            $this->data = parent::delete($value);
        }
        header("Location: " . $_SERVER["SCRIPT_NAME"]);
    }
}
class EntityRestApiHttpControl extends BaseEntityControler{
    public function process(){
        $method = $_SERVER['REQUEST_METHOD'];
        switch ($method) {
            case "GET":
                if(isset($_GET['id'])){
                    $this->data = parent::get($_GET['id']);
                }else{
                    if(isset($_GET['index'])){
                        $this->pagination->index = $_GET['index'];
                    }
                    if(isset($_GET['size'])){
                        $this->pagination->size = $_GET['size'];
                    }
                    $this->data = parent::list($this->pagination);
                }
                break;
            case "POST":
                if(isset($_GET['id'])){
                    $this->data = parent::update($_GET['id'],$_POST);
                }else{
                    $_POST['created'] = new \DateTime();
                    $this->data = parent::push($_POST);
                }
                break;
            case "DELETE":
                if(isset($_GET['id'])){
                    $this->data = parent::delete($_GET['id']);
                    header("Location: " . $_SERVER["SCRIPT_NAME"]);
                }
                break;
            default:
        }
        return $this;
    }
    public function render(){
        header('Content-Type: application/json');
        if($this->data == null) http_response_code(404);
        echo json_encode($this->data);
        exit();
    }
}
class Pagination{
    public $index = 0;
    public $size;
    public $max;
    public function result(&$data){
        $size = $this->size;
        if($size == null){
            $size = count($data);
        }
        $this->max = count($data) / $size;
        $data = array_slice((array)$data,$this->index * $size,$size);
    }
}
class Filter{
    public $config;
    public $data;
    public $callable;
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }
    public function result($data){
        $this->auto_result($data);
        return $data;
    }
    public function auto_result(&$data){
        $key = @$this->data['key'];
        $value = @$this->data['value'];
        $callable = $this->callable;
        if(isset($callable) && is_callable($callable)){
            $callable($data);
        }
        if(!isset($key)) return;
        foreach ($data as $k => $v) {
            if(!is_array($v)) continue;
            if(!isset($v[$key])){
                unset($data[$k]);
                continue;
            }
            foreach ($v as $k1 => $v1) {
                $g = @$this->data[$k1];
                if(!empty($g)){
                    if(!$this->equal_value($g,$v1,@$this->config[$k1])){
                        unset($data[$k]);
                    }
                    if($key == $g) continue;
                }
                if($key == $k1 && !empty($value)){
                    if(!$this->equal_value($value,$v1,@$this->config[$k1])){
                        unset($data[$k]);
                    }
                }
            }
        }
    }
    public function equal_value($d1,$d2,$options){
        if(is_array($options)){
            foreach ($options as $value) {
                switch ($value) {
                    case 'contain':
                        if(strpos($d2,$d1) !== false){
                            return true;
                        }
                        break;
                    case 'lowercase':
                        $d2 = strtolower($d2);
                        break;
                    default:
                        break;
                }
            }
        }
        return $d1 == $d2;
    }
}
class Sorter{
    public $name;
    public $type;
    public $mode;
    public function __construct($name,$type,$mode = 1)
    {
        $this->name = $name;
        $this->type = $type;
        $this->mode = $mode;
    }
    public function result(&$data){
        $arr = (array)$data;
        usort($arr,function($a,$b) {
            if(!is_array($a)) return 0;
            if(!is_array($b)) return 0;
            $ad = @$a[$this->name];
            $bd = @$b[$this->name];
            if ($ad == $bd) {
                return 0;
            }
            return $ad < $bd ? $this->mode * 1 : $this->mode * -1;
        });
        $data = $arr;
    }
}