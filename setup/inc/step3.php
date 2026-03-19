<?php
$status = $setup->requirementsCheck();
if ($status['error']) die('Failure. <a href="?step=1">back</a>');

$success = true;
if (isset($_POST['username']) && isset($_POST['password'])) {
    $hashed_password = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);
    $ps = $setup->db->prepare("INSERT INTO `magirc_admin` SET `username` = :username, `password` = :password");
    $ps->bindParam(':username', $_POST['username'], PDO::PARAM_STR);
    $ps->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    $success = $ps->execute();
}

$template = $setup->tpl->loadTemplate('step3.twig');
echo $template->render(array(
    'step' => 3,
    'admins' => $setup->checkAdmins(),
    'error' => !$success
));
