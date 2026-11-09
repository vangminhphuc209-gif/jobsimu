<?php
declare(strict_types=1);

/** Career/admissions phase P0-P1. Safe to call on every request; additive only. */
function admission_catalog_groups(): array {
    return [
        'An ninh và Quốc phòng','Báo chí và thông tin','Công nghệ thông tin',
        'Dịch vụ xã hội, cộng đồng và cá nhân','Du lịch, thể thao','Giáo dục và Đào tạo',
        'Khoa học sự sống','Khoa học tự nhiên','Khoa học xã hội và hành vi',
        'Kiến trúc xây dựng','Kinh doanh và quản lý','Kỹ thuật và công nghệ kỹ thuật',
        'Lãnh đạo, quản lý các ngành, các cấp','Môi trường và bảo vệ môi trường',
        'Nghệ thuật','Nhân văn','Nông nghiệp, lâm nghiệp và thủy sản',
        'Pháp luật','Quản lý trật tự an toàn giao thông','Sản xuất và chế biến',
        'Thú y','Toán và thống kê','Vận tải, phân phối và hậu cần',
        'Y tế và chăm sóc sức khỏe',
    ];
}
function admission_catalog_seed(): void {
    $db = db();
    $groups = admission_catalog_groups();
    $g = $db->prepare('INSERT INTO career_groups (slug,name,sort_order,source_url) VALUES (?,?,?,?)');
    foreach ($groups as $i => $name) {
        $g->execute(['nhom-' . ($i + 1),$name,$i + 1,'https://hocvienhuongnghiep.com/tu-dien-huong-nghiep/']);
    }
    // Existing careers remain untouched; this mapping only sets a missing group.
    $mapping = [
        'lap-trinh-vien'=>3,'ky-su-tri-tue-nhan-tao'=>3,'bac-si'=>24,
        'nha-thiet-ke-do-hoa'=>15,'giao-vien'=>6,'chuyen-vien-marketing'=>11,
        'kien-truc-su'=>10,'ky-su-co-khi'=>12,'chuyen-vien-tam-ly'=>9,
        'luat-su'=>18,'nha-bao-bien-tap-vien'=>2,'chuyen-vien-tai-chinh'=>11,
        'quan-tri-nhan-su'=>11,'ky-su-moi-truong'=>14,'dau-bep'=>20,
        'si-quan-quan-doi'=>1,'cong-an-nhan-dan'=>1,'dieu-duong'=>24,'cong-tac-xa-hoi'=>4
    ];
    $u = $db->prepare('UPDATE careers SET career_group_id=? WHERE slug=? AND career_group_id IS NULL');
    foreach ($mapping as $slug=>$groupId) $u->execute([$groupId,$slug]);

    // Editorial starter taxonomy: no unverified university fees or cutoffs.
    $majors = [
      ['7480101','Khoa học máy tính','Nghiên cứu thuật toán, hệ thống máy tính và phần mềm.'],
      ['7480201','Công nghệ thông tin','Phát triển, vận hành hệ thống và ứng dụng số.'],
      ['7140209','Sư phạm Toán học','Đào tạo giáo viên Toán và nghiệp vụ sư phạm.'],
      ['7140211','Sư phạm Vật lý','Đào tạo giáo viên Vật lý và nghiệp vụ sư phạm.'],
      ['7720101','Y khoa','Đào tạo kiến thức và thực hành y khoa.'],
      ['7720301','Điều dưỡng','Đào tạo kỹ năng chăm sóc và theo dõi người bệnh.'],
      ['7380101','Luật','Nghiên cứu pháp luật và thực tiễn pháp lý.'],
      ['7340115','Marketing','Phân tích thị trường, khách hàng và xây dựng chiến lược tiếp thị.'],
      ['7580101','Kiến trúc','Thiết kế và triển khai công trình kiến trúc.'],
      ['7520103','Kỹ thuật cơ khí','Thiết kế, sản xuất và vận hành hệ thống cơ khí.'],
      ['7310401','Tâm lý học','Nghiên cứu nhận thức, cảm xúc và hành vi.'],
      ['7320101','Báo chí','Nghiên cứu và thực hành báo chí đa phương tiện.'],
      ['7340201','Tài chính - Ngân hàng','Phân tích và vận hành các hoạt động tài chính.'],
      ['7760101','Công tác xã hội','Hỗ trợ cá nhân và cộng đồng tiếp cận dịch vụ.']
    ];
    $m=$db->prepare('INSERT INTO majors (code,name,description) VALUES (?,?,?)');
    foreach ($majors as $v) {
        // Uniqueness guard so existing user-edited content is not overwritten.
        $s=$db->prepare('SELECT id FROM majors WHERE code=?'); $s->execute([$v[0]]);
        if (!$s->fetchColumn()) $m->execute($v);
    }
    $links=[
      'lap-trinh-vien'=>['7480101','7480201'],'ky-su-tri-tue-nhan-tao'=>['7480101','7480201'],
      'giao-vien'=>['7140209','7140211'],'bac-si'=>['7720101'],
      'dieu-duong'=>['7720301'],'luat-su'=>['7380101'],
      'chuyen-vien-marketing'=>['7340115'],'kien-truc-su'=>['7580101'],
      'ky-su-co-khi'=>['7520103'],'chuyen-vien-tam-ly'=>['7310401'],
      'nha-bao-bien-tap-vien'=>['7320101'],'chuyen-vien-tai-chinh'=>['7340201'],
      'cong-tac-xa-hoi'=>['7760101']
    ];
    $link=$db->prepare('INSERT INTO career_major_paths(career_id,major_id) VALUES (?,?)');
    foreach($links as $slug=>$codes) {
      $s=$db->prepare('SELECT id FROM careers WHERE slug=?');$s->execute([$slug]);$cid=$s->fetchColumn();
      if(!$cid) continue;
      foreach($codes as $code) {
        $s=$db->prepare('SELECT id FROM majors WHERE code=?');$s->execute([$code]);$mid=$s->fetchColumn();
        $s=$db->prepare('SELECT 1 FROM career_major_paths WHERE career_id=? AND major_id=?');
        $s->execute([$cid,$mid]);
        if($mid && !$s->fetchColumn())$link->execute([$cid,$mid]);
      }
    }
    // Starter school directory, without pretending to have verified program-specific admissions records.
    $schools=[
      ['BKA','Đại học Bách khoa Hà Nội','Hà Nội','https://www.hust.edu.vn/'],
      ['QSB','Trường Đại học Bách khoa - Đại học Quốc gia Thành phố Hồ Chí Minh','TP. Hồ Chí Minh','https://hcmut.edu.vn/'],
      ['YHB','Trường Đại học Y Hà Nội','Hà Nội','https://hmu.edu.vn/'],
      ['SPH','Trường Đại học Sư phạm Hà Nội','Hà Nội','https://hnue.edu.vn/'],
      ['CSH','Học viện Cảnh sát nhân dân','Hà Nội','https://hvcsnd.edu.vn/']
    ];
    $ins=$db->prepare('INSERT INTO institutions(code,name,province,website) VALUES (?,?,?,?)');
    foreach($schools as $school) {
      $s=$db->prepare('SELECT 1 FROM institutions WHERE code=?');$s->execute([$school[0]]);
      if(!$s->fetchColumn())$ins->execute($school);
    }
}
function run_admissions_migrations(): void {
    $db=db(); $mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $num=$mysql?'INT UNSIGNED':'INTEGER';
    $text=$mysql?'VARCHAR(255)':'TEXT';
    $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $ddl=[
      "CREATE TABLE IF NOT EXISTS career_groups(id $id,slug VARCHAR(80) NOT NULL UNIQUE,name VARCHAR(180) NOT NULL,sort_order INTEGER NOT NULL DEFAULT 0,source_url VARCHAR(500) NULL)$suffix",
      "CREATE TABLE IF NOT EXISTS majors(id $id,code VARCHAR(30) NOT NULL UNIQUE,name VARCHAR(200) NOT NULL,description TEXT NULL)$suffix",
      "CREATE TABLE IF NOT EXISTS institutions(id $id,code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(220) NOT NULL,province VARCHAR(120) NULL,website VARCHAR(500) NULL,admissions_url VARCHAR(500) NULL,type VARCHAR(80) NULL)$suffix",
      "CREATE TABLE IF NOT EXISTS programs(id $id,institution_id $num NOT NULL,major_id $num NOT NULL,name VARCHAR(220) NOT NULL,program_code VARCHAR(80) NULL,degree_level VARCHAR(80) NULL,duration_semesters INTEGER NULL,credits_required INTEGER NULL,official_url VARCHAR(500) NULL,UNIQUE(institution_id,major_id,name),FOREIGN KEY(institution_id) REFERENCES institutions(id),FOREIGN KEY(major_id) REFERENCES majors(id))$suffix",
      "CREATE TABLE IF NOT EXISTS career_major_paths(career_id $num NOT NULL,major_id $num NOT NULL,PRIMARY KEY(career_id,major_id),FOREIGN KEY(career_id) REFERENCES careers(id) ON DELETE CASCADE,FOREIGN KEY(major_id) REFERENCES majors(id) ON DELETE CASCADE)$suffix",
      "CREATE TABLE IF NOT EXISTS admission_records(id $id,program_id $num NOT NULL,admission_year INTEGER NOT NULL,method VARCHAR(140) NOT NULL,combination VARCHAR(50) NOT NULL DEFAULT '',candidate_group VARCHAR(100) NOT NULL DEFAULT '',cutoff_score DECIMAL(8,3) NULL,score_scale VARCHAR(50) NULL,source_url VARCHAR(500) NOT NULL,verified_at DATETIME NULL,UNIQUE(program_id,admission_year,method,combination,candidate_group),FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE CASCADE)$suffix",
      "CREATE TABLE IF NOT EXISTS tuition_records(id $id,program_id $num NOT NULL,academic_year VARCHAR(20) NOT NULL,charge_basis VARCHAR(40) NOT NULL,amount_vnd BIGINT NULL,source_url VARCHAR(500) NOT NULL,verified_at DATETIME NULL,UNIQUE(program_id,academic_year,charge_basis),FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE CASCADE)$suffix",
      "CREATE TABLE IF NOT EXISTS admission_requirements(id $id,program_id $num NOT NULL,admission_year INTEGER NOT NULL,requirement_type VARCHAR(60) NOT NULL,details TEXT NOT NULL,source_url VARCHAR(500) NOT NULL,FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE CASCADE)$suffix",
      "CREATE TABLE IF NOT EXISTS admission_events(id $id,program_id $num NOT NULL,admission_year INTEGER NOT NULL,event_name VARCHAR(160) NOT NULL,starts_at DATE NULL,ends_at DATE NULL,source_url VARCHAR(500) NOT NULL,FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE CASCADE)$suffix"
    ];
    foreach($ddl as $sql)$db->exec($sql);
    if($mysql) {
      $columns=$db->query('SHOW COLUMNS FROM careers')->fetchAll(PDO::FETCH_COLUMN);
    } else {
      $columns=array_column($db->query('PRAGMA table_info(careers)')->fetchAll(PDO::FETCH_ASSOC),'name');
    }
    if(!in_array('career_group_id',$columns,true)) $db->exec('ALTER TABLE careers ADD COLUMN career_group_id '.($mysql?'INT UNSIGNED':'INTEGER').' NULL');
    // Lock seed to one-time installation. Do not overwrite editorial changes on every request.
    $count=(int)$db->query('SELECT COUNT(*) FROM career_groups')->fetchColumn();
    if($count===0) admission_catalog_seed();
}
