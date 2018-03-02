# yami
the life of a student
<?php

$row = 1;
$f=fopen("bookCorrect.csv", "w");
if (($handle = fopen("book.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $num = count($data);
        $arr[] = $data;
    }
}

array_shift($arr);
//print_r($arr);

$csvLine = "id" . " ; " . "İsim" . " ; " . "Category" . " ; " . "Issue" . " ; " . "Copyright". " ; " . "ISBN" . " ; " . "Language" . " ; " . "Publisher" . " ; " . "PrintLocation" . " ; " . "Author" . " ; " . "Demirbas" . "\n";

if($csvLine){
    fwrite($f, $csvLine, strlen($csvLine));
}
foreach($arr as $dizi) {
    $bul = array(".gsi", ".dergi");
    $degistir = array("_page_1.tif", "_page_1.pdf");
    $url = "http://192.168.1.106:4242/gsi/books/" . str_replace($bul, $degistir, $dizi[0]);
    $replace = str_replace($bul, $degistir, $dizi[0]);
    $icerik = file_get_contents($url);
    $icerik = json_decode($icerik);



    if (($icerik->found === true))
    {

        if (($icerik->_source->CommitDesc) != null) {
            $CommitDesc = ($icerik->_source->CommitDesc);
        } else {
            $CommitDesc = "bulunamadı";
        }
        echo "\n";
        if (($icerik->_source->Category) != null) {
            $Category = ($icerik->_source->Category);
        } else {
            $Category=  "bulunamadı";
        }
        echo "\n";
        if (($icerik->_source->Issue) != null) {
            $Issue = ($icerik->_source->Issue);
        } else {
            $Issue = "bulunamadı";
        }
        echo "\n";
        if (($icerik->_source->Copyright) != null) {
            $Copyright = ($icerik->_source->Copyright);
        } else {
            $Copyright= "bulunamadı";
        }
        echo "\n";
        if (($icerik->_source->ISBN) != null) {
            $ISBN = ($icerik->_source->ISBN);
        } else {
            $ISBN = "bulunamadı";
        }
        echo "\n";
        if (($icerik->_source->Language) != null) {
            $Language = ($icerik->_source->Language);
        } else {
            $Publisher = "bulunamadı";
        }
        echo "\n";
        if (($icerik->_source->Publisher) != null) {
            $Publisher = ($icerik->_source->Publisher);
        } else {
            $Publisher = "bulunamadı";
        }
        echo "\n";
        if (($icerik->_source->PrintLocation) != null) {
            $PrintLocation = ($icerik->_source->PrintLocation);
        } else {
            $PrintLocation= "bulunamadı";
        }
        echo "\n";
        if (($icerik->_source->Author) != null) {
            $Author = ($icerik->_source->Author);
        } else {
            $Author = "bulunamadı";
        }
        echo "\n";
        if (($icerik->_source->Demirbas) != null) {
            $Demirbas = ($icerik->_source->Demirbas);
        } else {
            $Demirbas = "bulunamadı";
        }

        $csvLine = $replace . " ; " . $CommitDesc . " ; " . $Category . " ; " . $Issue . " ; " . $Copyright . " ; " . $ISBN . " ; " . $Language . " ; " . $Publisher . " ; " . $PrintLocation . " ; " . $Author . " ; " . $Demirbas . "\n";
    }

    else{
        $ffbul = array(".dergi",".gsi");
        $ffdegistir = array("_page_1.tif","_page_1.pdf");
        $ffurl = "http://192.168.1.106:4242/gsi/books/" . str_replace($ffbul, $ffdegistir, $dizi[0]);
        $ffreplace = str_replace($ffbul, $ffdegistir, $dizi[0]);
        $fficerik = file_get_contents($ffurl);
        $fficerik = json_decode($fficerik);
        if (($fficerik->_source->CommitDesc) != null) {
            $CommitDesc = ($fficerik->_source->CommitDesc);
        } else {
            $CommitDesc = "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->CommitDesc) != null) {
            $CommitDesc = ($fficerik->_source->CommitDesc);
        } else {
            $CommitDesc = "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->Category) != null) {
            $Category = ($fficerik->_source->Category);
        } else {
            $Category=  "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->Issue) != null) {
            $Issue = ($fficerik->_source->Issue);
        } else {
            $Issue = "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->Copyright) != null) {
            $Copyright = ($fficerik->_source->Copyright);
        } else {
            $Copyright= "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->ISBN) != null) {
            $ISBN = ($fficerik->_source->ISBN);
        } else {
            $ISBN = "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->Language) != null) {
            $Language = ($fficerik->_source->Language);
        } else {
            $Language = "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->Publisher) != null) {
            $Publisher = ($fficerik->_source->Publisher);
        } else {
            $Publisher = "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->PrintLocation) != null) {
            $PrintLocation = ($fficerik->_source->PrintLocation);
        } else {
            $PrintLocation= "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->Author) != null) {
            $Author = ($fficerik->_source->Author);
        } else {
            $Author = "bulunamadı";
        }
        echo "\n";
        if (($fficerik->_source->Demirbas) != null) {
            $Demirbas = ($fficerik->_source->Demirbas);
        } else {
            $Demirbas = "bulunamadı";
        }

        $csvLine = $ffreplace . ";" . $CommitDesc ." ; " . $Category . " ; " . $Issue . " ; " . $Copyright . " ; " . $ISBN . " ; ". $Language . " ; " . $Publisher . " ; " . $PrintLocation . " ; " . $Author . " ; " . $Demirbas . "\n";
    }
    if($csvLine){
        fwrite($f, $csvLine, strlen($csvLine));
    }
}
