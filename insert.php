<?php
//エラー表示
ini_set("display_errors",1);


//データ取得
$spotName  = $_POST["spotName"];
$lat       = $_POST["lat"];
$lng       = $_POST["lng"];
$tripDate  = $_POST["tripDate"];
$spendTime = $_POST["spendTime"];
$cost      = $_POST["cost"];
$score     = $_POST["score"];
$comment   = $_POST["comment"];


//db接続
// localhost用
// include("funcs.php");
// $pdo = db_conn();

// さくら用
include("funcs.php");
$pdo = db_conn_sakura();



//データ登録
$sql = "INSERT INTO trip(spotName, lat, lng, tripDate, spendTime, cost, score, comment, indate) VALUES (:spotName, :lat, :lng, :tripDate, :spendTime, :cost, :score, :comment, sysdate())";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':spotName', $spotName);
$stmt->bindValue(':lat', $lat);
$stmt->bindValue(':lng', $lng);
$stmt->bindValue(':tripDate', $tripDate);
$stmt->bindValue(':spendTime', $spendTime);
$stmt->bindValue(':cost', $cost);
$stmt->bindValue(':score', $score);
$stmt->bindValue(':comment', $comment);
$status = $stmt->execute();


//処理後
if($status == false){
    $sql_error($stmt);
    exit;
}


//今登録したtripのidを取得する（写真をどの投稿に紐づけるか特定するため）
$tripId = $pdo->lastInsertId();

//写真アップロード処理（複数枚対応）
if (isset($_FILES["photo"]) && is_array($_FILES["photo"]["name"])) {

    $uploadDir = __DIR__ . "/photoUpload/";

    //選択された写真の枚数分ループする
    $fileCount = count($_FILES["photo"]["name"]);

    for ($i = 0; $i < $fileCount; $i++) {

        //そのファイルが正常にアップロードされているかチェック
        if ($_FILES["photo"]["error"][$i] !== UPLOAD_ERR_OK) {
            continue; //エラーがあればこの1枚だけスキップ
        }

        //ファイル名が重複しないようにユニークな名前へ変換
        $ext = pathinfo($_FILES["photo"]["name"][$i], PATHINFO_EXTENSION);
        $fileName = uniqid("photo_", true) . "." . $ext;
        $destination = $uploadDir . $fileName;

        //一時保存されているファイルを本来の場所へ移動
        if (move_uploaded_file($_FILES["photo"]["tmp_name"][$i], $destination)) {

            //photosテーブルに1枚ずつ登録
            $photoPath = "photoUpload/" . $fileName;
            $photoSql = "INSERT INTO photos(trip_id, path) VALUES (:trip_id, :path)";
            $photoStmt = $pdo->prepare($photoSql);
            $photoStmt->bindValue(':trip_id', $tripId, PDO::PARAM_INT);
            $photoStmt->bindValue(':path', $photoPath);
            $photoStmt->execute();
        }
    }
}

//処理後
redirect("sns.php");

?>