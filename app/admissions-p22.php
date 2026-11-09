<?php
declare(strict_types=1);

/** Published institution-level ranges: never represented as exact per-program tuition. */
function admissions_p22_migrate(): void {
    $db=db();
    $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $mysql=$driver==='mysql';
    $id=$mysql?'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk=$mysql?'INT UNSIGNED':'INTEGER';
    $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $db->exec("CREATE TABLE IF NOT EXISTS institution_tuition_bands (
      id $id,
      institution_id $fk NOT NULL,
      academic_year VARCHAR(9) NOT NULL,
      program_group VARCHAR(100) NOT NULL,
      charge_basis VARCHAR(30) NOT NULL,
      minimum_vnd BIGINT NOT NULL,
      maximum_vnd BIGINT NOT NULL,
      note TEXT NULL,
      source_url VARCHAR(500) NOT NULL,
      source_title VARCHAR(240) NOT NULL,
      verified_on DATE NOT NULL,
      UNIQUE(institution_id,academic_year,program_group,charge_basis),
      FOREIGN KEY(institution_id) REFERENCES institutions(id) ON DELETE CASCADE
    )$suffix");
}
/** Curated tuition ranges taken from admissions announcements, not estimated costs. */
function admissions_p22_tuition_seed(): int {
    $db=db();
    $s=$db->prepare('SELECT id FROM institutions WHERE code=?');
    $s->execute(['BKA']);$schoolId=$s->fetchColumn();
    if(!$schoolId)return 0;
    $source2024='https://smse.hust.edu.vn/vi/news/tin-bai/diem-chuan-dai-hoc-bach-khoa-ha-noi-2024-118.html';
    $source2025='https://hust.edu.vn/vi/news/hoat-dong-chung/dai-hoc-bach-khoa-ha-noi-cong-bo-diem-chuan-xet-tuyen-dai-hoc-nam-2025-655575.html';
    $data=[
       ['2024-2025','Chương trình chuẩn',12000000,15000000,$source2024,'Thông báo ĐHBK Hà Nội 2024'],
       ['2024-2025','Elitech',16500000,21000000,$source2024,'Thông báo ĐHBK Hà Nội 2024'],
       ['2024-2025','Song bằng, hợp tác quốc tế',22500000,29000000,$source2024,'Thông báo ĐHBK Hà Nội 2024'],
       ['2024-2025','IT-E10 và EM-E14',32000000,33500000,$source2024,'Thông báo ĐHBK Hà Nội 2024'],
       ['2025-2026','Chương trình chuẩn',14000000,17500000,$source2025,'Thông báo ĐHBK Hà Nội 2025'],
       ['2025-2026','Elitech',17500000,22500000,$source2025,'Thông báo ĐHBK Hà Nội 2025'],
       ['2025-2026','Song bằng, hợp tác quốc tế',25500000,30000000,$source2025,'Thông báo ĐHBK Hà Nội 2025'],
       ['2025-2026','IT-E10 và EM-E14',32000000,33500000,$source2025,'Thông báo ĐHBK Hà Nội 2025']
    ];
    $exists=$db->prepare('SELECT id FROM institution_tuition_bands WHERE institution_id=? AND academic_year=? AND program_group=? AND charge_basis=?');
    $insert=$db->prepare('INSERT INTO institution_tuition_bands (institution_id,academic_year,program_group,charge_basis,minimum_vnd,maximum_vnd,note,source_url,source_title,verified_on) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $count=0;
    foreach($data as $item){
      [$year,$group,$min,$max,$source,$title]=$item;
      $exists->execute([$schoolId,$year,$group,'semester']);
      if($exists->fetchColumn())continue; // Preserve editorial/admin corrections.
      $note='Khoảng học phí theo nhóm chương trình và học kỳ, không phải học phí chính xác của từng ngành, chưa bao gồm chi phí sinh hoạt.';
      $insert->execute([$schoolId,$year,$group,'semester',$min,$max,$note,$source,$title,'2026-11-09']);
      $count++;
    }
    return $count;
}
/** Returns comparable min/max increases as context, not a single student-specific raise. */
function admissions_p22_band_change(array $earlier,array $later): ?array {
    if(($earlier['program_group']??'')!==($later['program_group']??'')||
       ($earlier['charge_basis']??'')!==($later['charge_basis']??'')||
       !preg_match('/^20\\d{2}-20\\d{2}$/',(string)($earlier['academic_year']??''))||
       !preg_match('/^20\\d{2}-20\\d{2}$/',(string)($later['academic_year']??''))||
       (int)substr((string)$later['academic_year'],0,4)!==(int)substr((string)$earlier['academic_year'],0,4)+1||
       (float)($earlier['minimum_vnd']??0)<=0||(float)($earlier['maximum_vnd']??0)<=0)return null;
    return [
      'min_percent'=>round(((float)$later['minimum_vnd']/(float)$earlier['minimum_vnd']-1)*100,2),
      'max_percent'=>round(((float)$later['maximum_vnd']/(float)$earlier['maximum_vnd']-1)*100,2)
    ];
}
