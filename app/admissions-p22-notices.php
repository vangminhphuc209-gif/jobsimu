<?php
declare(strict_types=1);

/** Historical school-level notices. Do not bind military/civilian rules to an unrelated programme. */
function admissions_p22_notice_migrate(): void {
    $db=db();$mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk=$mysql?'INT UNSIGNED':'INTEGER';
    $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $db->exec("CREATE TABLE IF NOT EXISTS institution_admission_notices(
       id $id,institution_id $fk NOT NULL,admission_year INTEGER NOT NULL,
       notice_key VARCHAR(64) NOT NULL,track VARCHAR(40) NOT NULL,
       notice_type VARCHAR(45) NOT NULL,title VARCHAR(240) NOT NULL,
       description TEXT NOT NULL,starts_on DATE NULL,ends_on DATE NULL,
       source_url VARCHAR(500) NOT NULL,checked_on DATE NOT NULL,
       UNIQUE(institution_id,admission_year,notice_key),
       FOREIGN KEY(institution_id) REFERENCES institutions(id) ON DELETE CASCADE
    )$suffix");
}
function admissions_p22_notice_seed(): int {
    $db=db();$created=0;
    $schools=[
      ['KQH','Học viện Kỹ thuật Quân sự','Hà Nội','https://mta.edu.vn/'],
    ];
    foreach($schools as $data){
      $s=$db->prepare('SELECT id FROM institutions WHERE code=?');$s->execute([$data[0]]);
      if(!$s->fetchColumn()){
        $s=$db->prepare('INSERT INTO institutions(code,name,province,website) VALUES(?,?,?,?)');$s->execute($data);
      }
    }
    $notices=[
      ['KQH',2026,'military-prescreening','military','requirement',
       'Hệ quân sự: phải qua sơ tuyển theo Bộ Quốc phòng',
       'Thí sinh đăng ký xét tuyển đại học, cao đẳng hệ quân sự năm 2026 phải qua sơ tuyển và đáp ứng tiêu chuẩn Bộ Quốc phòng. Học viện có thể có điều kiện bổ sung theo đối tượng tuyển sinh; kiểm tra quyết định chính thức.',
       null,null,'https://mod.gov.vn/!ut/p/z1/zZLfT8IwEMf_Gh4v7VbW7XWoyCAwgyCsL6b7AauydkAB9a-3Q59cYBo0sUl77eV7vbtPixiaIyb5Xiy5FkrylTlHjD52_dHY61g-Dt2gi4P-zYAMQ2zhaxfNvgjCXiWw6cAJqB1YDmLficcnho-b4h8QQyyRutQ5igqVtvCWg7GwFTo7HrTePn9uYF1y2cJ7LiE2M1clyJxr2GeQ5BmkyrhAbxSkXIBcKuNVxu6UgL3INEheQGK8Si6NpFqEzCHZgVHtVFKFcFEVVCYiRZGXOhnFZAExsTNoJwsbOLE84Jx6TrqwCfXsGsBah-w8nz5iy5WKP95KPK3XzDdAlNTZi0bz_0FkVjE516VTE9T_UROIyIB0T6aYUDQzFR_QVKpNYWjd__Cdek0ZSPvCDA0NuL97Pb47Eib-cBJYOKTWn1ZPLsVfFtNp4ZFXYDEd9946I7i9ir3DZFEcjf8Ouou1-A!!/dz/d5/L2dBISEvZ0FBIS9nQSEh/?1dmy=&current=true&urile=wcm%3Apath%3A%2Fmod%2Fsa-mod-site%2Fsa-ttsk%2Fsa-tt-qpan%2Fve-to-chuc-chi-tieu-tuyen-sinh-dai-hoc-cao-dang-quan-su-he-chinh-quy-cac-truong-quan-doi-nam-2026'],
      ['KQH',2026,'prescreen-window','military','historical_deadline',
       'Mốc sơ tuyển quân sự 2026',
       'Sơ tuyển theo thông tin năm 2026 diễn ra từ 10/02/2026 đến 15/04/2026; chỉ dùng tham khảo lịch sử, không suy ra lịch 2027.',
       '2026-02-10','2026-04-15','https://mod.gov.vn/wcm/connect/mod/sa-mod-site/sa-ttsk/sa-tt-qpan/them-phuong-thuc-xet-tuyen-moi-cho-ky-tuyen-sinh-quan-su-nam-2026'],
      ['KQH',2026,'civilian-track','civilian','requirement',
       'Hệ dân sự: cách tuyển sinh khác hệ quân sự',
       'Theo thông tin tuyển sinh dân sự 2026, học viện tuyển sinh trong cả nước, gồm xét tuyển thẳng, đánh giá năng lực và thi THPT. Không áp yêu cầu sơ tuyển quân sự cho hồ sơ dân sự.',
       null,null,'https://www.qdnd.vn/giao-duc-khoa-hoc/nha-truong-quan-doi/tuyen-sinh-dan-su-nam-2026-cua-hoc-vien-ky-thuat-quan-su-co-gi-can-chu-y-1036679'],
      ['CSH',2026,'police-screening','police','requirement',
       'Công an: xác minh sơ tuyển và điều kiện riêng',
       'Tuyển sinh Học viện Cảnh sát nhân dân có điều kiện và phân nhóm đối tượng; thông tin về sức khỏe, tiêu chuẩn chính trị và sơ tuyển phải đối chiếu với quy định Bộ Công an năm tuyển sinh. Công cụ tra cứu không kết luận đủ hay thiếu điều kiện dự tuyển.',
       null,null,'https://www.hvcsnd.edu.vn/thong-tin-cap-nhat-bo-sung-ve-tuyen-sinh-trinh-do-dai-hoc-tai-hoc-vien-csnd-nam-hoc-2026-2027-13930'],
      ['KHA',2026,'online-enrolment','general','historical_deadline',
       'NEU: thời gian nhập học trực tuyến 2026',
       'Theo thông tin trường, lịch nhập học trực tuyến 8:00 ngày 15/08/2026 đến 16:00 ngày 23/08/2026. Đây là mốc lịch sử.',
       '2026-08-15','2026-08-23','https://fit.neu.edu.vn/post/diem-chuan-dai-hoc-chinh-quy-2026-toan-bo-nganh']
    ];
    $insert=$db->prepare('INSERT INTO institution_admission_notices(institution_id,admission_year,notice_key,track,notice_type,title,description,starts_on,ends_on,source_url,checked_on) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $exists=$db->prepare('SELECT 1 FROM institution_admission_notices WHERE institution_id=? AND admission_year=? AND notice_key=?');
    $find=$db->prepare('SELECT id FROM institutions WHERE code=?');
    foreach($notices as $notice){
      [$code,$year,$key,$track,$type,$title,$desc,$start,$end,$url]=$notice;
      $find->execute([$code]);$schoolId=$find->fetchColumn();
      if(!$schoolId)continue; // Do not create an unsourced institution for notices.
      $exists->execute([$schoolId,$year,$key]);
      if($exists->fetchColumn())continue;
      $insert->execute([$schoolId,$year,$key,$track,$type,$title,$desc,$start,$end,$url,'2026-11-09']);$created++;
    }
    return $created;
}
