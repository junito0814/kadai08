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
$id        = $_POST["id"];

//db接続
// localhost用
include("funcs.php");
$pdo = db_conn();

// さくら用
// include("funcs.php");
// $pdo = db_conn_sakura();


//データ登録
$sql = "UPDATE trip SET spotName= :spotName, lat= :lat, lng= :lng, tripDate= :tripDate, spendTime= :spendTime, cost= :cost, score= :score, comment= :comment WHERE id= :id";
$stmt = $pdo->prepare($sql);

$stmt->bindValue(':spotName', $spotName, PDO::PARAM_STR);
$stmt->bindValue(':lat', $lat, PDO::PARAM_STR);
$stmt->bindValue(':lng', $lng, PDO::PARAM_STR);
$stmt->bindValue(':tripDate', $tripDate, PDO::PARAM_STR);
$stmt->bindValue(':spendTime', $spendTime, PDO::PARAM_INT);
$stmt->bindValue(':cost', $cost, PDO::PARAM_INT);
$stmt->bindValue(':score', $score, PDO::PARAM_INT);
$stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$status = $stmt->execute();

//処理後
if($status == false){
    $sql_error($stmt);
}

//新しく写真が選ばれていれば、photosテーブルに追加登録する
if (isset($_FILES["photo"]) && is_array($_FILES["photo"]["name"])) {

    $uploadDir = __DIR__ . "/photoUpload/";

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

            //photosテーブルに1枚ずつ追加登録
            $photoPath = "photoUpload/" . $fileName;
            $photoSql = "INSERT INTO photos(trip_id, path) VALUES (:trip_id, :path)";
            $photoStmt = $pdo->prepare($photoSql);
            $photoStmt->bindValue(':trip_id', $id, PDO::PARAM_INT);
            $photoStmt->bindValue(':path', $photoPath);
            $photoStmt->execute();
        }
    }
}

//処理後
redirect("sns.php");

?>