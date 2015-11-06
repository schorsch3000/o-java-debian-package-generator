#!/usr/bin/env php
<?php
define('MAINTAINER_REALNAME', 'Joe Tester');
define('MAINTAINER_EMAIL', 'joe@foo.bar');
define('REPO_NAME', 'jessy');
define('STORAGE_PATH', './artifacts');
chdir(__DIR__);
$errors = [];

$executables = ['wget', 'yes', 'make-jpkg'];

foreach ($executables as $executable) {
    if (!(bool)shell_exec('which ' . escapeshellarg($executable))) {
        $errors[] = "can't execute $executable";
    }
}
if (count($errors)) {
    echo "found errors:\n";
    foreach ($errors as $error) {
        echo " - ", $error, "\n";
    }
    exit;
}


$overviewPage = new DOMDocument();
@$overviewPage->loadHTMLFile('http://www.oracle.com/technetwork/java/javase/downloads/index.html');
$xpath = new DOMXPath($overviewPage);
$downloadPages = $xpath->query("//a[contains(@href,'downloads/jdk') and not(contains(@href,'-arm-') or contains(@href,'-netbeans-'))]/@href");


$jdks = [];
if (is_null($downloadPages)) {
    echo "Can't find any packages\n";
    die(1);
}
foreach ($downloadPages as $element) {
    $relLink = $element->textContent;
    if (!preg_match('#downloads/(jdk\d+)-downloads#', $relLink, $m)) {
        continue;
    }
    $version = $m[1];
    $absLink = "http://www.oracle.com" . $relLink;
    $downloadPage = new DOMDocument();
    @$downloadPage->loadHTMLFile($absLink);
    $xpath = new DOMXPath($downloadPage);
    $script = $xpath->query('//script[contains(./text(),"linux-x64.tar.gz")]/text()');

    if (is_null($script)) {
        continue;
    }
    foreach ($script as $element) {
        $scriptText = $element->textContent;
        preg_match_all('#linux-[^-]*?.tar.gz.*?(\{.*?\})#', $scriptText, $m);
        foreach ($m[1] as $downloadJson) {
            $itemData = json_decode($downloadJson);
            preg_match('#jdk/([^/]*?)/#', $itemData->filepath, $mm);
            if ($itemData->title !== 'Linux x64') {
                continue;
            }
            $jdks[$version][$mm[1]] = $itemData->filepath;
            chdir(__DIR__);
            $dir = STORAGE_PATH . "/$version/{$mm[1]}";
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            chdir($dir);
            if (!count(glob('*.deb'))) {
                system('wget -qc --header "Cookie: oraclelicense=a" ' . escapeshellarg($itemData->filepath));
                system('df -h');
                system('yes| make-jpkg --full-name ' . escapeshellarg(MAINTAINER_REALNAME) . ' --email ' . escapeshellarg(MAINTAINER_EMAIL) . ' *.tar.gz');
            }
            if (count(glob('*.deb')) && count(glob('*.tar.gz'))) {
                system('rm *.tar.gz');
            }
        }
    }
}