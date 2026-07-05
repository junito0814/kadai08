<?php
//エラー表示
ini_set("display_errors",1);

//db接続
// localhost用
// include("funcs.php");
// $pdo = db_conn();

// さくら用
include("funcs.php");
$pdo = db_conn_sakura();

//送られてきたidを取得
$id = $_POST["id"] ?? null;

//idが無い、または数値でなければ処理を止める
if ($id === null || !is_numeric($id)) {
    http_response_code(400);
    exit(json_encode(["error" => "invalid id"]));
}

//いいね数を+1する
$stmt = $pdo->prepare("UPDATE trip SET likeCount = likeCount + 1 WHERE id = :id");
$stmt->bindValue(":id", $id, PDO::PARAM_INT);
$stmt->execute();

//更新後の最新の件数を取得する
$selectStmt = $pdo->prepare("SELECT likeCount FROM trip WHERE id = :id");
$selectStmt->bindValue(":id", $id, PDO::PARAM_INT);
$selectStmt->execute();
$row = $selectStmt->fetch(PDO::FETCH_ASSOC);

//JSON形式で画面（JS側）に結果を返す
header("Content-Type: application/json");
echo json_encode(["likeCount" => $row["likeCount"]]);
?>