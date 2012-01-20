<?php
    // modify this to point to your book directory
    $bookdir = '/home/andi/Dropbox/ebooks/';





    error_reporting(E_ALL ^ E_NOTICE);
    header('Content-Type: text/html; charset=utf-8');

    require('epub.php');
    try{
        $book = $_REQUEST['book'];
        $book = str_replace('..','',$book); // no upper dirs, lowers might be supported later
        $epub = new EPub($bookdir.$book.'.epub');
    }catch (Exception $e){
        $error = $e->getMessage();
    }

    if($_REQUEST['save']){
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

        try{
            $epub->save();
        }catch(Exception $e){
            $error = $e->getMessage();
        }
    }

?>
<html>
<head>
    <title>e-Book Manager</title>
    <link href="style.css" rel="stylesheet" type="text/css" />
    <script type="text/javascript">
        <?php if($error) echo "alert('".htmlspecialchars($error)."');";?>
    </script>
</head>
<body>

<div id="wrapper">
    <ul id="booklist">
        <?php
            $list = glob($bookdir.'/*.epub');
            foreach($list as $book){
                $base = basename($book,'.epub');
                echo '<li><a href="?book='.htmlspecialchars($base).'">'.htmlspecialchars($base).'</a></li>';
            }
        ?>
    </ul>

    <?php if($epub): ?>
    <form action="" method="post" id="bookpanel">
        <input type="hidden" name="book" value="<?php echo htmlspecialchars($_REQUEST['book'])?>" />

        <table>
            <tr>
                <th>Title</th>
                <td><input type="text" name="title" value="<?php echo htmlspecialchars($epub->Title())?>" /></td>
            </tr>
            <tr>
                <th>Authors</th>
                <td>
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
                <th>Description</th>
                <td><textarea name="description"><?php echo htmlspecialchars($epub->Description())?></textarea></td>
            </tr>
            <tr>
                <th>Subjects</th>
                <td><input type="text" name="subjects"  value="<?php echo htmlspecialchars(join(', ',$epub->Subjects()))?>" /></td>
            </tr>
            <tr>
                <th>Language</th>
                <td><p><input type="text" name="language"  value="<?php echo htmlspecialchars($epub->Language())?>" /></p></td>
            </tr>
            <tr>
                <th>Publisher</th>
                <td><p><input type="text" name="publisher" value="<?php echo htmlspecialchars($epub->Publisher())?>" /></p></td>
            </tr>
            <tr>
                <th>Copyright</th>
                <td><p><input type="text" name="copyright" value="<?php echo htmlspecialchars($epub->Copyright())?>" /></p></td>
            </tr>
            <tr>
                <th>ISBN</th>
                <td><p><input type="text" name="isbn"      value="<?php echo htmlspecialchars($epub->ISBN())?>" /></p></td>
            </tr>
        </table>
        <div class="center">
            <input name="save" type="submit" />
        </div>
    </form>
    <?php endif; ?>

</div>
</body>
</html>
