<?php

if( !function_exists('inspect') ){
  function inspect(){
    echo '<pre>' . print_r(func_get_args(), true) . '</pre>';
  }
}