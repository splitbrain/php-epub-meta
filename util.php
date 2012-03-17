<?php

function to_file($input){
    $input = str_replace(' ','_',$input);
    $input = str_replace('__','_',$input);
    $input = str_replace(',_',',',$input);
    $input = str_replace('_,',',',$input);
    $input = str_replace('-_',',',$input);
    $input = str_replace('_-',',',$input);
    return $input;
}

function book_output($input){
    $input = str_replace('_',' ',$input);
    $input = str_replace(',',', ',$input);

    list($author,$title) = explode('-',$input,2);

    if(!$title){
        $title = $author;
        $author = '';
    }

    return '<span class="title">'.htmlspecialchars($title).'</span>'.
           '<span class="author">'.htmlspecialchars($author).'</author>';
}
