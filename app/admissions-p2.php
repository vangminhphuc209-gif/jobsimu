<?php
declare(strict_types=1);

/**
 * P2 admissions data pipeline.
 * Rows are staged, reviewed by a privileged admin, and committed atomically.
 * No web scraping, inferred cutoffs, fabricated fees, or automatic verification.
 */
function admissions_p2_migrate(): void {
    $db=db();
    $mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $body=$mysql?'LONGTEXT':'TEXT';
    $db->exec("CREATE TABLE IF NOT EXISTS admission_import_batches (
      id $id, submitted_by INTEGER NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending',
      file_name VARCHAR(180) NOT NULL, item_count INTEGER NOT NULL,
      payload $body NOT NULL, error_note TEXT NULL,
      created_at DATETIME NOT NULL, reviewed_at DATETIME NULL,
      reviewed_by INTEGER NULL
    )$suffix");
    $db->exec("CREATE TABLE IF NOT EXISTS admission_sources (
      id $id, source_url VARCHAR(500) NOT NULL, title VARCHAR(250) NOT NULL,
      publisher VARCHAR(180) NULL, source_year INTEGER NULL,
      checked_at DATETIME NOT NULL, batch_id INTEGER NULL,
      INDEX_PLACEHOLDER
    )$suffix" === '' ? '' : ""); // no-op placeholder; table created below portably
    $db->exec("CREATE TABLE IF NOT EXISTS admission_source_documents (
      id $id, batch_id INTEGER NULL, source_url VARCHAR(500) NOT NULL,
      source_title VARCHAR(250) NOT NULL, source_year INTEGER NULL,
      checked_at DATETIME NOT NULL
    )$suffix");
}
function admissions_p2_http_url($url): bool {
    if(!is_string($url) || strlen($url)>500 || !filter_var($url,FILTER_VALIDATE_URL)) return false;
    $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
    $host=(string)parse_url($url,PHP_URL_HOST);
    return in_array($scheme,['http','https'],true) && $host!=='';
}
function admissions_p2_required_headers(): array {
    return ['type','institution_code','institution_name','province','website',
      'major_code','major_name','program_name','program_code',
      'year','method','combination','candidate_group','score','score_scale',
      'academic_year','charge_basis','amount_vnd','source_url','source_title'];
}
/** Returns cleaned row, or throws on missing/inconsistent input. */
function admissions_p2_validate_row(array $row, int $line): array {
    foreach(admissions_p2_required_headers() as $key) $row[$key]=trim((string)($row[$key]??''));
    $type=strtolower($row['type']);
    if(!in_array($type,['cutoff','tuition'],true)) throw new InvalidArgumentException("Dòng $line: type chỉ nhận cutoff hoặc tuition.");
    foreach(['institution_code','institution_name','major_code','major_name','program_name','source_url','source_title'] as $key)
        if($row[$key]==='') throw new InvalidArgumentException("Dòng $line: thiếu $key.");
    if(!preg_match('/^[A-Za-z0-9_-]{2,40}$/D',$row['institution_code']))throw new InvalidArgumentException("Dòng $line: mã trường không hợp lệ.");
    if(!preg_match('/^[A-Za-z0-9_.-]{2,30}$/D',$row['major_code']))throw new InvalidArgumentException("Dòng $line: mã ngành không hợp lệ.");
    $caps=['institution_name'=>220,'major_name'=>200,'program_name'=>180,'program_code'=>80,
      'province'=>120,'source_title'=>250,'method'=>140,'combination'=>50,'candidate_group'=>100,
      'academic_year'=>20,'charge_basis'=>40,'score_scale'=>50];
    foreach($caps as $k=>$max)if(mb_strlen($row[$k])>$max)throw new InvalidArgumentException("Dòng $line: $k vượt quá giới hạn.");
    if(!admissions_p2_http_url($row['source_url']))throw new InvalidArgumentException("Dòng $line: source_url phải là HTTP(S) hợp lệ.");
    if($row['website']!=='' && !admissions_p2_http_url($row['website']))throw new InvalidArgumentException("Dòng $line: website không hợp lệ.");
    if($type==='cutoff'){
      if(!ctype_digit($row['year']) || (int)$row['year']<2010 || (int)$row['year']>(int)date('Y')+1)throw new InvalidArgumentException("Dòng $line: năm tuyển sinh không hợp lệ.");
      if($row['method']==='' || $row['score_scale']==='' || $row['score']==='' ||
         !is_numeric($row['score']) || !is_finite((float)$row['score']) ||
         (float)$row['score']<0 || (float)$row['score']>1000)throw new InvalidArgumentException("Dòng $line: thiếu/sai điểm, phương thức hoặc thang điểm.");
      if(!preg_match('/^\\d+(?:\\.\\d{1,3})?$/D',$row['score']))throw new InvalidArgumentException("Dòng $line: điểm phải có tối đa 3 chữ số thập phân.");
    }else{
      if(!preg_match('/^20\\d{2}-20\\d{2}$/D',$row['academic_year']))throw new InvalidArgumentException("Dòng $line: năm học phải dạng 2025-2026.");
      [$start,$end]=array_map('intval',explode('-',$row['academic_year']));
      if($end!==$start+1 || $start>date('Y')+1)throw new InvalidArgumentException("Dòng $line: năm học không hợp lệ.");
      if(!in_array($row['charge_basis'],['credit','semester','academic_year','whole_course','exempt'],true))throw new InvalidArgumentException("Dòng $line: charge_basis không hợp lệ.");
      if($row['amount_vnd']==='' || !ctype_digit($row['amount_vnd']) || (float)$row['amount_vnd']>5000000000)throw new InvalidArgumentException("Dòng $line: số tiền VND phải không âm và dưới 5 tỷ.");
      if($row['charge_basis']==='exempt' && (int)$row['amount_vnd']!==0)throw new InvalidArgumentException("Dòng $line: miễn học phí phải ghi 0.");
    }
    return $row;
}
/** Strict CSV parsing with a header row; UTF-8; maximum 500 data rows. */
function admissions_p2_read_csv(string $path): array {
    if(!is_file($path) || filesize($path)===false || filesize($path)>2*1024*1024)throw new InvalidArgumentException('CSV vượt 2MB hoặc không thể đọc.');
    $f=fopen($path,'rb');if(!$f)throw new InvalidArgumentException('Không mở được CSV.');
    try {
      $headers=fgetcsv($f);
      if(!$headers)throw new InvalidArgumentException('CSV trống.');
      $headers[0]=preg_replace('/^\\xEF\\xBB\\xBF/','',$headers[0]);
      $headers=array_map('trim',$headers);
      if(count($headers)!==count(array_unique($headers)))throw new InvalidArgumentException('CSV có cột trùng.');
      foreach(admissions_p2_required_headers() as $h)if(!in_array($h,$headers,true))throw new InvalidArgumentException("CSV thiếu cột $h.");
      $out=[];$line=1;$unique=[];
      while(($cells=fgetcsv($f))!==false){
        ++$line;
        if(count($cells)===1 && trim((string)$cells[0])==='')continue;
        if(count($cells)!==count($headers))throw new InvalidArgumentException("Dòng $line: sai số lượng cột.");
        if(count($out)>=500)throw new InvalidArgumentException('Giới hạn 500 dòng mỗi lần nhập.');
        $row=admissions_p2_validate_row(array_combine($headers,$cells),$line);
        $key=implode('|',[$row['type'],$row['institution_code'],$row['major_code'],
             $row['program_name'],$row['type']==='cutoff'?$row['year']:$row['academic_year'],
             $row['type']==='cutoff'?$row['method']:$row['charge_basis'],
             $row['combination'],$row['candidate_group']]);
        if(isset($unique[$key]))throw new InvalidArgumentException("Dòng $line: trùng khóa dữ liệu với dòng ".$unique[$key].".");
        $unique[$key]=$line;
        $out[]=$row;
      }
      if(!$out)throw new InvalidArgumentException('CSV không có dòng dữ liệu.');
      return $out;
    }finally{fclose($f);}
}
/** Caller must wrap this in a transaction. Only validated, approved rows reach public tables. */
function admissions_p2_apply_row(array $row,int $batchId): void {
    $db=db();
    $s=$db->prepare('SELECT id FROM institutions WHERE code=?');$s->execute([$row['institution_code']]);$schoolId=$s->fetchColumn();
    if(!$schoolId){
      $s=$db->prepare('INSERT INTO institutions(code,name,province,website) VALUES(?,?,?,?)');
      $s->execute([$row['institution_code'],$row['institution_name'],$row['province']?:null,$row['website']?:null]);
      $schoolId=$db->lastInsertId();
    }
    $s=$db->prepare('SELECT id FROM majors WHERE code=?');$s->execute([$row['major_code']]);$majorId=$s->fetchColumn();
    if(!$majorId){
      $s=$db->prepare('INSERT INTO majors(code,name,description) VALUES(?,?,NULL)');
      $s->execute([$row['major_code'],$row['major_name']]);$majorId=$db->lastInsertId();
    }
    $s=$db->prepare('SELECT id FROM programs WHERE institution_id=? AND major_id=? AND name=?');
    $s->execute([$schoolId,$majorId,$row['program_name']]);$programId=$s->fetchColumn();
    if(!$programId){
      $s=$db->prepare('INSERT INTO programs(institution_id,major_id,name,program_code,official_url) VALUES(?,?,?,?,?)');
      $s->execute([$schoolId,$majorId,$row['program_name'],$row['program_code']?:null,$row['source_url']]);
      $programId=$db->lastInsertId();
    }
    $s=$db->prepare('INSERT INTO admission_source_documents(batch_id,source_url,source_title,source_year,checked_at) VALUES(?,?,?,?,?)');
    $s->execute([$batchId,$row['source_url'],$row['source_title'],
      $row['type']==='cutoff'?(int)$row['year']:(int)substr($row['academic_year'],0,4),date('Y-m-d H:i:s')]);
    if($row['type']==='cutoff'){
      $pk=[$programId,(int)$row['year'],$row['method'],$row['combination'],$row['candidate_group']];
      $s=$db->prepare('SELECT id FROM admission_records WHERE program_id=? AND admission_year=? AND method=? AND combination=? AND candidate_group=?');
      $s->execute($pk);$id=$s->fetchColumn();
      if($id){
        $s=$db->prepare('UPDATE admission_records SET cutoff_score=?,score_scale=?,source_url=?,verified_at=? WHERE id=?');
        $s->execute([(float)$row['score'],$row['score_scale'],$row['source_url'],date('Y-m-d H:i:s'),$id]);
      }else{
        $s=$db->prepare('INSERT INTO admission_records(program_id,admission_year,method,combination,candidate_group,cutoff_score,score_scale,source_url,verified_at) VALUES(?,?,?,?,?,?,?,?,?)');
        $s->execute(array_merge($pk,[(float)$row['score'],$row['score_scale'],$row['source_url'],date('Y-m-d H:i:s')]));
      }
    }else{
      $s=$db->prepare('SELECT id FROM tuition_records WHERE program_id=? AND academic_year=? AND charge_basis=?');
      $s->execute([$programId,$row['academic_year'],$row['charge_basis']]);$id=$s->fetchColumn();
      if($id){
        $s=$db->prepare('UPDATE tuition_records SET amount_vnd=?,source_url=?,verified_at=? WHERE id=?');
        $s->execute([(int)$row['amount_vnd'],$row['source_url'],date('Y-m-d H:i:s'),$id]);
      }else{
        $s=$db->prepare('INSERT INTO tuition_records(program_id,academic_year,charge_basis,amount_vnd,source_url,verified_at) VALUES(?,?,?,?,?,?)');
        $s->execute([$programId,$row['academic_year'],$row['charge_basis'],(int)$row['amount_vnd'],$row['source_url'],date('Y-m-d H:i:s')]);
      }
    }
}
function admissions_p2_approve_batch(int $batchId,int $adminId): int {
    $db=db();$db->beginTransaction();
    try{
      $sql='SELECT * FROM admission_import_batches WHERE id=?';
      if($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
      $s=$db->prepare($sql);$s->execute([$batchId]);$batch=$s->fetch();
      if(!$batch || $batch['status']!=='pending')throw new InvalidArgumentException('Lô nhập không tồn tại hoặc đã xử lý.');
      $rows=json_decode($batch['payload'],true);
      if(!is_array($rows) || count($rows)!==(int)$batch['item_count'])throw new RuntimeException('Dữ liệu lô nhập không hợp lệ.');
      foreach($rows as $i=>$row){
        $clean=admissions_p2_validate_row($row,$i+2);
        admissions_p2_apply_row($clean,$batchId);
      }
      $s=$db->prepare("UPDATE admission_import_batches SET status='approved',reviewed_by=?,reviewed_at=? WHERE id=? AND status='pending'");
      $s->execute([$adminId,date('Y-m-d H:i:s'),$batchId]);
      if($s->rowCount()!==1)throw new RuntimeException('Không thể xác nhận lô nhập.');
      $db->commit();return count($rows);
    }catch(Throwable $ex){if($db->inTransaction())$db->rollBack();throw $ex;}
}
/** Growth only for consecutive, comparable academic years with a positive base. */
function admissions_p2_tuition_growth(array $earlier,array $later): ?float {
    if(($earlier['charge_basis']??'')!==($later['charge_basis']??'') ||
       !preg_match('/^20\\d{2}-20\\d{2}$/',(string)($earlier['academic_year']??'')) ||
       !preg_match('/^20\\d{2}-20\\d{2}$/',(string)($later['academic_year']??'')) ||
       (int)substr($later['academic_year'],0,4)!==(int)substr($earlier['academic_year'],0,4)+1 ||
       !isset($earlier['amount_vnd'],$later['amount_vnd']) ||
       (float)$earlier['amount_vnd']<=0)return null;
    return round(((float)$later['amount_vnd']/(float)$earlier['amount_vnd']-1)*100,2);
}
