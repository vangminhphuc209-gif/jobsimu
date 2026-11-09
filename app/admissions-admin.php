<?php
declare(strict_types=1);

/** Loaded from admin.php after require_admin(). */
if(is_post()){
    verify_csrf();
    $intent=(string)($_POST['intent']??'');
    try{
      if($intent==='upload'){
        if(empty($_FILES['admissions_csv']) || (int)($_FILES['admissions_csv']['error']??99)!==UPLOAD_ERR_OK)
            throw new InvalidArgumentException('Không thể tải tệp CSV lên.');
        $file=$_FILES['admissions_csv'];
        if((int)$file['size']>2*1024*1024 || !is_uploaded_file($file['tmp_name']))
            throw new InvalidArgumentException('Tệp phải là CSV tải từ trình duyệt, tối đa 2MB.');
        $rows=admissions_p2_read_csv($file['tmp_name']);
        $json=json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $name=mb_substr(basename((string)$file['name']),0,180);
        $s=db()->prepare("INSERT INTO admission_import_batches(submitted_by,status,file_name,item_count,payload,created_at) VALUES(?,'pending',?,?,?,?)");
        $s->execute([(int)user()['id'],$name,count($rows),$json,date('Y-m-d H:i:s')]);
        $id=(int)db()->lastInsertId();
        audit('Tải CSV tuyển sinh để duyệt','admission_import_batch',$id);
        flash('success','Đã kiểm tra CSV. Vui lòng rà soát nguồn và xác nhận duyệt thủ công.');
        redirect('admin.php?section=admissions&batch='.$id);
      }
      if($intent==='approve' || $intent==='reject'){
        $id=max(1,(int)($_POST['batch_id']??0));
        if($intent==='approve'){
          $count=admissions_p2_approve_batch($id,(int)user()['id']);
          audit('Duyệt dữ liệu tuyển sinh','admission_import_batch',$id);
          flash('success',"Đã duyệt $count bản ghi và lưu nguồn.");
        }else{
          $s=db()->prepare("UPDATE admission_import_batches SET status='rejected',reviewed_at=?,reviewed_by=? WHERE id=? AND status='pending'");
          $s->execute([date('Y-m-d H:i:s'),(int)user()['id'],$id]);
          if(!$s->rowCount())throw new InvalidArgumentException('Lô dữ liệu đã xử lý hoặc không tồn tại.');
          audit('Từ chối dữ liệu tuyển sinh','admission_import_batch',$id);
          flash('success','Đã từ chối lô nhập; không thay đổi dữ liệu hiển thị.');
        }
        redirect('admin.php?section=admissions');
      }
    }catch(Throwable $ex){
      flash('error','Không thể xử lý lô nhập: '.$ex->getMessage());
      redirect('admin.php?section=admissions');
    }
}
$batchId=max(0,(int)($_GET['batch']??0));$batch=null;$preview=[];
if($batchId){
  $s=db()->prepare('SELECT * FROM admission_import_batches WHERE id=?');
  $s->execute([$batchId]);$batch=$s->fetch();
  if($batch)$preview=json_decode($batch['payload'],true)?:[];
}
$batches=db()->query('SELECT id,file_name,item_count,status,created_at,reviewed_at FROM admission_import_batches ORDER BY id DESC LIMIT 50')->fetchAll();
$recordCount=(int)db()->query('SELECT COUNT(*) FROM admission_records')->fetchColumn();
$feeCount=(int)db()->query('SELECT COUNT(*) FROM tuition_records')->fetchColumn();
$schoolCount=(int)db()->query('SELECT COUNT(*) FROM institutions')->fetchColumn();
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nhập dữ liệu tuyển sinh · JOBSIMU</title><link rel="stylesheet" href="<?=url('assets/css/app.css')?>"><link rel="stylesheet" href="<?=url('assets/css/admin.css')?>"></head><body>
<div class="container" style="max-width:1150px;margin:35px auto">
  <p><a href="<?=url('admin.php')?>">← Quay lại Admin</a> · <a href="<?=url('index.php?page=university-compare')?>">Xem so sánh trường ↗</a></p>
  <h1>Quản lý dữ liệu tuyển sinh (P2)</h1>
  <p class="muted">Chỉ quản trị viên mới có quyền nhập và duyệt dữ liệu. Trước khi duyệt, hãy mở từng đường dẫn nguồn để kiểm tra năm, phương thức, chương trình và học phí. Hệ thống không tự xác nhận tính chính xác của nguồn.</p>
  <?php foreach(pull_flashes() as $f):?><div class="alert alert-<?=e($f['type'])?>" role="alert"><?=e($f['message'])?></div><?php endforeach;?>
  <div class="grid-3">
    <div class="card"><strong><?=number_format($schoolCount)?></strong><p>Trường trong danh mục</p></div>
    <div class="card"><strong><?=number_format($recordCount)?></strong><p>Bản ghi điểm chuẩn</p></div>
    <div class="card"><strong><?=number_format($feeCount)?></strong><p>Bản ghi học phí</p></div>
  </div>
  <section class="card" style="margin-top:24px">
    <h2>1. Tải CSV để kiểm tra trước khi duyệt</h2>
    <p>CSV UTF-8 có dòng tiêu đề. Mỗi dòng là <code>cutoff</code> hoặc <code>tuition</code>; tối đa 500 dòng, 2MB. Không tải dữ liệu thiếu nguồn hoặc số liệu chưa kiểm chứng.</p>
    <p><a href="<?=url('docs/admissions-p2-import.md')?>">Hướng dẫn định dạng & quy trình nhập dữ liệu</a></p>
    <p><a href="<?=url('docs/admissions-p22-official-cutoffs.csv')?>" download>CSV 40 điểm chuẩn đã đối chiếu từ HUST và HNUE (2023–2025) — xem và xác minh trước khi duyệt ↗</a></p>
    <form method="post" enctype="multipart/form-data">
      <?=csrf_field()?><input type="hidden" name="intent" value="upload">
      <div class="form-group"><label for="admissions_csv">Chọn tệp CSV</label><input class="input" id="admissions_csv" type="file" name="admissions_csv" accept=".csv,text/csv" required></div>
      <button class="btn btn-primary">Tải và kiểm tra dữ liệu</button>
    </form>
  </section>
  <?php if($batch):?>
  <section class="card" style="margin-top:24px">
    <h2>2. Xem trước lô #<?=(int)$batch['id']?> · <?=e($batch['file_name'])?></h2>
    <p>Trạng thái: <strong><?=e($batch['status'])?></strong> · <?=count($preview)?> bản ghi. Kiểm tra nguồn và thông số từng dòng trước khi duyệt.</p>
    <div style="overflow-x:auto;max-height:520px">
      <table style="min-width:920px;width:100%"><thead><tr><th>Loại</th><th>Trường</th><th>Ngành/chương trình</th><th>Năm</th><th>Phương thức/cơ sở tính</th><th>Giá trị</th><th>Đề án/nguồn</th></tr></thead><tbody>
      <?php foreach($preview as $row):?>
        <tr><td><?=e($row['type'])?></td><td><?=e($row['institution_code'])?> · <?=e($row['institution_name'])?></td>
        <td><?=e($row['major_code'])?> · <?=e($row['program_name'])?></td>
        <td><?=e($row['type']==='cutoff'?$row['year']:$row['academic_year'])?></td>
        <td><?=e($row['type']==='cutoff'?$row['method'].' '.$row['combination'].' / '.$row['score_scale']:$row['charge_basis'])?></td>
        <td><?=e($row['type']==='cutoff'?$row['score']:$row['amount_vnd'])?></td>
        <td><a href="<?=e(safe_external_url($row['source_url']))?>" target="_blank" rel="noopener noreferrer"><?=e($row['source_title'])?> ↗</a></td></tr>
      <?php endforeach;?></tbody></table>
    </div>
    <?php if($batch['status']==='pending'):?>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px">
      <form method="post" onsubmit="return confirm('Bạn đã đối chiếu từng nguồn chính thức và muốn công bố toàn bộ lô này?')">
        <?=csrf_field()?><input type="hidden" name="intent" value="approve"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>">
        <button class="btn btn-primary" type="submit">Duyệt và công bố dữ liệu</button>
      </form>
      <form method="post"><?=csrf_field()?><input type="hidden" name="intent" value="reject"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>"><button class="btn btn-outline">Từ chối</button></form>
    </div>
    <?php endif;?>
  </section><?php endif;?>
  <section class="card" style="margin-top:24px"><h2>Lịch sử lô nhập</h2>
    <div style="overflow-x:auto"><table style="width:100%"><thead><tr><th>Mã</th><th>Tệp</th><th>Số dòng</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead><tbody>
    <?php foreach($batches as $b):?><tr><td>#<?=(int)$b['id']?></td><td><?=e($b['file_name'])?></td><td><?=(int)$b['item_count']?></td><td><?=e($b['status'])?></td><td><?=e($b['created_at'])?></td><td><a href="<?=url('admin.php?section=admissions&batch='.(int)$b['id'])?>">Xem</a></td></tr><?php endforeach;?>
    </tbody></table></div>
  </section>
</div></body></html>
<?php exit;
