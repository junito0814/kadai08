<?php
$id = $_GET["id"];

//db接続
// localhost用
include("funcs.php");
$pdo = db_conn();

// さくら用
// include("funcs.php");
// $pdo = db_conn_sakura();

//先に、この投稿に紐づく写真のパスを全部取得しておく
//（trip を消してしまうと、phptoテーブルのどのtrip_idを消せばいいかが分からなくなるため）
$photoStmt = $pdo->prepare("SELECT path FROM photos WHERE trip_id = :id");
$photoStmt->bindValue(":id", $id, PDO::PARAM_INT);
$photoStmt->execute();
$photos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);

//サーバー上の実ファイルを1枚ずつ削除する
foreach ($photos as $photoPath) {
    $filePath = __DIR__ . "/" . $photoPath;
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}


//データ削除
//sql削除
$stmt = $pdo->prepare("DELETE FROM trip WHERE id = :id");
$stmt -> bindValue(":id", $id, PDO::PARAM_INT);
$status = $stmt->execute();


//処理後
if($status == false){
    $sql_error($stmt);
}else{
    redirect("sns.php");
}





?>
