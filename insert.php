<?php
//エラー表示
ini_set("display_errors",1);


//データ取得
$spotName  = trim($_POST["spotName"] ?? "");
$lat       = trim($_POST["lat"] ?? "");
$lng       = trim($_POST["lng"] ?? "");
$tripDate  = trim($_POST["tripDate"] ?? "");
$spendTime = trim($_POST["spendTime"] ?? "");
$cost      = trim($_POST["cost"] ?? "");
$score     = trim($_POST["score"] ?? "");
$comment   = trim($_POST["comment"] ?? "");

if ($spotName === "") {
    $spotName = "未設定";
}

if ($lat === "") {
    $lat = 0;
}

if ($lng === "") {
    $lng = 0;
}

if ($tripDate === "") {
    $tripDate = date("Y-m-d");
}

if ($spendTime === "") {
    $spendTime = 0;
}

if ($cost === "") {
    $cost = 0;
}

if ($score === "") {
    $score = 0;
}

if ($comment === "") {
    $comment = "";
}


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

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            error_log("Upload directory could not be created: " . $uploadDir);
        }
    }

    if (is_dir($uploadDir) && !is_writable($uploadDir)) {
        @chmod($uploadDir, 0777);
    }

    //選択された写真の枚数分ループする
    $fileCount = count($_FILES["photo"]["name"]);

    for ($i = 0; $i < $fileCount; $i++) {
        $fileError = $_FILES["photo"]["error"][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($fileError !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpName = $_FILES["photo"]["tmp_name"][$i] ?? "";
        $originalName = $_FILES["photo"]["name"][$i] ?? "";
        if ($tmpName === "" || !is_uploaded_file($tmpName)) {
            continue;
        }

        //ファイル名が重複しないようにユニークな名前へ変換
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        $safeExt = $safeExt !== '' ? '.' . $safeExt : '';
        $fileName = uniqid("photo_", true) . $safeExt;
        $destination = $uploadDir . $fileName;

        //一時保存されているファイルを本来の場所へ移動
        if (move_uploaded_file($tmpName, $destination)) {
            try {
                //photosテーブルに1枚ずつ登録
                $photoPath = "photoUpload/" . $fileName;
                $photoSql = "INSERT INTO photos(trip_id, path) VALUES (:trip_id, :path)";
                $photoStmt = $pdo->prepare($photoSql);
                $photoStmt->bindValue(':trip_id', $tripId, PDO::PARAM_INT);
                $photoStmt->bindValue(':path', $photoPath);
                $photoStmt->execute();
            } catch (PDOException $e) {
                error_log("Photo insert failed: " . $e->getMessage());
            }
        } else {
            error_log("Photo move failed: " . $tmpName . " -> " . $destination);
        }
    }
}

//処理後
redirect("sns.php");

?>