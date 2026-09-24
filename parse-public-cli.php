<?php
// Local helper. Only reads HTML on stdin, returns validated public price JSON.
function add_action(...$args){}
function mlf_catalog(){return array_map(fn($a)=>['asin'=>$a],['B01N074ELB','B076ZR13BJ','B07HDGLR6N','B0FP1YYNP9']);}
class WP_Error{function __construct(public $code,public $message){}function get_error_message(){return $this->message;}}
require __DIR__.'/prices-public-module.php';
$result=MLF_Amazon_Prices::parse_public(stream_get_contents(STDIN),$argv[1]??'',time());
if($result instanceof WP_Error){fwrite(STDERR,$result->get_error_message());exit(2);}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
