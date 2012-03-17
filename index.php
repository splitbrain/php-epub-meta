<?php
    // modify this to point to your book directory
    $bookdir = '/home/andi/Dropbox/ebooks/';


    error_reporting(E_ALL ^ E_NOTICE);

    // proxy google requests
    if(isset($_GET['api'])){
        header('application/json; charset=UTF-8');
        echo file_get_contents('https://www.googleapis.com/books/v1/volumes?q='.rawurlencode($_GET['api']).'&maxResults=25&printType=books&projection=full');
        exit;
    }

    // load epub data
    require('epub.php');
    if(isset($_REQUEST['book'])){
        try{
            $book = $_REQUEST['book'];
            $book = str_replace('..','',$book); // no upper dirs, lowers might be supported later
            $epub = new EPub($bookdir.$book.'.epub');
        }catch (Exception $e){
            $error = $e->getMessage();
        }
    }

    // return image data
    if(isset($_REQUEST['img']) && isset($epub)){
        $img = $epub->Cover();
        header('Content-Type: '.$img['mime']);
        echo $img['data'];
        exit;
    }

    // save epub data
    if($_REQUEST['save'] && isset($epub)){
        $epub->Title($_POST['title']);
        $epub->Description($_POST['description']);
        $epub->Language($_POST['language']);
        $epub->Publisher($_POST['publisher']);
        $epub->Copyright($_POST['copyright']);
        $epub->ISBN($_POST['isbn']);
        $epub->Subjects($_POST['subjects']);

        $authors = array();
        foreach((array) $_POST['authorname'] as $num => $name){
            if($name){
                $as = $_POST['authoras'][$num];
                if(!$as) $as = $name;
                $authors[$as] = $name;
            }
        }
        $epub->Authors($authors);

        // handle image
        $cover = '';
        if(preg_match('/^https?:\/\//i',$_POST['coverurl'])){
            $data = @file_get_contents($_POST['coverurl']);
            if($data){
                $cover = tempnam(sys_get_temp_dir(), 'epubcover');
                file_put_contents($cover,$data);
                unset($data);
            }
        }elseif(is_uploaded_file($_FILES['coverfile']['tmp_name'])){
            $cover = $_FILES['coverfile']['tmp_name'];
        }
        if($cover){
            $info = @getimagesize($cover);
            if(preg_match('/^image\/(gif|jpe?g|png)$/',$info['mime'])){
                $epub->Cover($cover,$info['meta']);
            }else{
                $error = "Not a valid image file".$cover;
            }
        }

        try{
            $epub->save();
        }catch(Exception $e){
            $error = $e->getMessage();
        }

        if($cover) @unlink($cover);
    }

    header('Content-Type: text/html; charset=utf-8');
?>
<html>
<head>
    <title>e-Book Manager</title>
    <link href="style.css" rel="stylesheet" type="text/css" />

    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1/jquery-ui.min.js"></script>
    <link href="https://ajax.googleapis.com/ajax/libs/jqueryui/1/themes/smoothness/jquery-ui.css" rel="stylesheet" type="text/css" />

    <script type="text/javascript" src="https://raw.github.com/cleditor/cleditor/master/jquery.cleditor.js"></script>
    <link href="https://raw.github.com/cleditor/cleditor/master/jquery.cleditor.css" rel="stylesheet" type="text/css" />

    <script type="text/javascript" src="script.js"></script>

    <script type="text/javascript">
        <?php if($error) echo "alert('".htmlspecialchars($error)."');";?>

        var $wysiwg = null;
        $(function() {
                $wysiwyg = $('textarea').cleditor({
                width: 465,
                controls:     // controls to add to the toolbar
                        "bold italic underline strikethrough subscript superscript | " +
                        "style removeformat | bullets numbering | " +
                        "alignleft center alignright justify | undo redo | " +
                        "link unlink | source",
                styles:       // styles in the style popup
                        [["Paragraph", "<p>"], ["Header 1", "<h1>"], ["Header 2", "<h2>"],
                        ["Header 3", "<h3>"],  ["Header 4","<h4>"],  ["Header 5","<h5>"]]
            });
        });
    </script>
</head>
<body>

<div id="wrapper">
    <ul id="booklist">
        <?php
            $list = glob($bookdir.'/*.epub');
            foreach($list as $book){
                $base = basename($book,'.epub');
                echo '<li '.($base == $_REQUEST['book'] ? 'class="active"' : '' ).'>';
                echo '<a href="?book='.htmlspecialchars($base).'">'.htmlspecialchars($base).'</a>';
                echo '</li>';
            }
        ?>
    </ul>

    <?php if($epub): ?>
    <form action="" method="post" id="bookpanel" enctype="multipart/form-data">
        <input type="hidden" name="book" value="<?php echo htmlspecialchars($_REQUEST['book'])?>" />

        <table>
            <tr>
                <th>Title</th>
                <td><input type="text" name="title" value="<?php echo htmlspecialchars($epub->Title())?>" /></td>
            </tr>
            <tr>
                <th>Authors</th>
                <td id="authors">
                    <?php
                        $count = 0;
                        foreach($epub->Authors() as $as => $name){
                    ?>
                            <p>
                                <input type="text" name="authorname[<?php echo $count?>]" value="<?php echo htmlspecialchars($name)?>" />
                                (<input type="text" name="authoras[<?php echo $count?>]" value="<?php echo htmlspecialchars($as)?>" />)
                            </p>
                    <?php
                            $count++;
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <th>Description<br />
                    <img src="?book=<?php echo htmlspecialchars($_REQUEST['book'])?>&amp;img=1" id="cover" width="90" />
                </th>
                <td><textarea name="description"><?php echo htmlspecialchars($epub->Description())?></textarea></td>
            </tr>
            <tr>
                <th>Subjects</th>
                <td><input type="text" name="subjects"  value="<?php echo htmlspecialchars(join(', ',$epub->Subjects()))?>" /></td>
            </tr>
            <tr>
                <th>Publisher</th>
                <td><input type="text" name="publisher" value="<?php echo htmlspecialchars($epub->Publisher())?>" /></td>
            </tr>
            <tr>
                <th>Copyright</th>
                <td><input type="text" name="copyright" value="<?php echo htmlspecialchars($epub->Copyright())?>" /></td>
            </tr>
            <tr>
                <th>Language</th>
                <td><p><input type="text" name="language"  value="<?php echo htmlspecialchars($epub->Language())?>" /></p></td>
            </tr>
            <tr>
                <th>ISBN</th>
                <td><p><input type="text" name="isbn"      value="<?php echo htmlspecialchars($epub->ISBN())?>" /></p></td>
            </tr>
            <tr>
                <th>Cover-Image</th>
                <td><p>
                    <input type="file" name="coverfile" />
                    or URL: <input type="text" name="coverurl" value="" />
                </p></td>
        </table>
        <div class="center">
            <input name="save" type="submit" />
        </div>
    </form>
    <?php endif; ?>

</div>
</body>
</html>
