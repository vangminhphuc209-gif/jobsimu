<?php
declare(strict_types=1);

/** This map is the only source of SQL identifiers used by the Hub 1 editor. */
function knowledge_admin_config(string $kind): array {
    $configs = [
        'articles' => ['table'=>'knowledge_articles','label'=>'Bài viết kiến thức','statuses'=>['draft'=>'Ẩn / Bản nháp','published'=>'Công khai'],'fields'=>[
            'title'=>['Tiêu đề',200,true], 'slug'=>['Đường dẫn (chữ thường, số, dấu gạch ngang)',220,true],
            'category'=>['Chuyên mục',['admissions'=>'Tuyển sinh','trend'=>'Xu hướng nghề','guide'=>'Cẩm nang'],true],
            'summary'=>['Tóm tắt',400,true], 'content'=>['Nội dung (văn bản thuần)',60000,true],
            'source_name'=>['Tên nguồn',120,false], 'source_url'=>['URL nguồn',500,false],
            'related_career_slug'=>['Slug nghề liên quan',180,false], 'featured'=>['Bài nổi bật','checkbox',false],
        ]],
        'portals' => ['table'=>'external_portals','label'=>'Cổng thông tin','statuses'=>['inactive'=>'Ẩn','active'=>'Hiển thị'],'fields'=>[
            'title'=>['Tên cổng thông tin',150,true], 'organization'=>['Đơn vị phụ trách',150,true],
            'icon'=>['Biểu tượng',20,true], 'description'=>['Mô tả',300,true], 'target_url'=>['URL đích',500,true],
            'category'=>['Nhóm',['official_admissions'=>'Tuyển sinh chính thức','career_directory'=>'Danh mục nghề','scholarship'=>'Học bổng','skills'=>'Kỹ năng'],true],
            'sort_order'=>['Thứ tự hiển thị','integer',true],
        ]],
    ];
    return $configs[$kind] ?? [];
}

function knowledge_admin_handle(string $section): void {
    if (!in_array($section,['knowledge-articles-save','knowledge-portals-save','knowledge-articles-toggle','knowledge-portals-toggle'],true)) return;
    require_admin();
    if (!is_post()) { http_response_code(405); header('Allow: POST'); exit('Thao tác này cần gửi biểu mẫu.'); }
    verify_csrf();
    $kind = strpos($section,'knowledge-articles-')===0 ? 'articles' : 'portals';
    $cfg = knowledge_admin_config($kind);
    $table = $cfg['table'];
    $idRaw = $_POST['id'] ?? '0';
    $id = is_scalar($idRaw) ? filter_var($idRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]) : false;
    if ($id === false) { http_response_code(422); exit('Mã bản ghi không hợp lệ.'); }
    $listUrl = 'admin.php?section=knowledge-'.$kind;
    try {
        $status = $_POST['status'] ?? '';
        if (!is_string($status) || !array_key_exists($status,$cfg['statuses'])) throw new InvalidArgumentException('Trạng thái không hợp lệ.');
        if ($id > 0) {
            $stmt=db()->prepare("SELECT id FROM {$table} WHERE id=?");$stmt->execute([$id]);
            if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Bản ghi không còn tồn tại.');
        }
        if (substr($section,-7)==='-toggle') {
            if (!$id) throw new InvalidArgumentException('Chưa chọn bản ghi.');
            $stmt=db()->prepare("UPDATE {$table} SET status=? WHERE id=?");$stmt->execute([$status,$id]);
        } else {
            $values=[];
            foreach ($cfg['fields'] as $field=>$meta) {
                [$label,$rule,$required]=$meta;
                $raw=$_POST[$field] ?? '';
                if (!is_string($raw)) throw new InvalidArgumentException($label.': giá trị không hợp lệ.');
                $value=trim($raw);
                if ($rule==='checkbox') { $value=$value==='1'?1:0; }
                elseif ($rule==='integer') {
                    $value=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>-100000,'max_range'=>100000]]);
                    if ($value===false) throw new InvalidArgumentException($label.': nhập số nguyên từ -100000 đến 100000.');
                } elseif (is_array($rule)) {
                    if (!array_key_exists($value,$rule)) throw new InvalidArgumentException($label.': lựa chọn không hợp lệ.');
                } else {
                    if (($required && $value==='') || mb_strlen($value)>$rule) throw new InvalidArgumentException($label.': bắt buộc nếu có dấu *, tối đa '.$rule.' ký tự.');
                    if ($field==='content' && strlen($value)>60000) throw new InvalidArgumentException('Nội dung vượt quá dung lượng lưu trữ (60000 byte).');
                    if (in_array($field,['source_url','target_url'],true) && $value!=='' && safe_external_url($value)==='') throw new InvalidArgumentException($label.': chỉ chấp nhận URL HTTP/HTTPS đầy đủ.');
                    if ($field==='slug' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$value)) throw new InvalidArgumentException('Slug chỉ gồm chữ thường không dấu, số và dấu gạch ngang.');
                    if ($field==='related_career_slug' && $value!=='') {
                        $stmt=db()->prepare('SELECT id FROM careers WHERE slug=?');$stmt->execute([$value]);
                        if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Slug nghề liên quan không tồn tại.');
                    }
                }
                $values[$field]=$value;
            }
            $values['status']=$status;
            $columns=array_keys($values);
            if ($id) {
                $assignments=implode(',',array_map(static function(string $field): string { return $field.'=?'; },$columns));
                $stmt=db()->prepare("UPDATE {$table} SET {$assignments} WHERE id=?");$stmt->execute(array_merge(array_values($values),[$id]));
            } else {
                $stmt=db()->prepare("INSERT INTO {$table} (".implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');$stmt->execute(array_values($values));$id=(int)db()->lastInsertId();
            }
        }
        audit('Cập nhật nội dung Hub 1',$table,$id);
        flash('success','Đã lưu '.mb_strtolower($cfg['label']).'.');
    } catch (InvalidArgumentException $error) {
        flash('error',$error->getMessage());
    } catch (PDOException $error) {
        if ($error->getCode()!=='23000') throw $error;
        flash('error','Đường dẫn bài viết đã tồn tại hoặc dữ liệu liên kết không hợp lệ.');
    }
    redirect($listUrl);
}

function knowledge_admin_render(string $section): void {
    $kind=$section==='knowledge-articles'?'articles':'portals';
    $cfg=knowledge_admin_config($kind);$table=$cfg['table'];
    $stmt=db()->prepare("SELECT * FROM {$table} ORDER BY id DESC");$stmt->execute();$rows=$stmt->fetchAll();
    $editing=null;$editId=isset($_GET['edit']) && is_scalar($_GET['edit'])?(int)$_GET['edit']:0;
    foreach($rows as $row)if((int)$row['id']===$editId)$editing=$row;
    $defaults=$kind==='articles'?['category'=>'guide','status'=>'draft']:['category'=>'career_directory','icon'=>'🌐','sort_order'=>0,'status'=>'inactive'];
    $record=$editing??$defaults;
    ?>
    <div class="toolbar"><p class="muted">Thêm nội dung, sửa nguồn và thay đổi trạng thái công khai. Bài viết lưu dưới dạng văn bản thuần, không thực thi HTML.</p><a class="btn btn-outline" href="<?=url('index.php?page=knowledge-hub')?>">Xem Hub 1</a></div>
    <div class="card table-wrap"><table><thead><tr><th>Tiêu đề</th><th>Nhóm</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?=e($row['title'])?></td><td><?=e($cfg['fields']['category'][1][$row['category']]??$row['category'])?></td><td><?=e($cfg['statuses'][$row['status']]??$row['status'])?></td><td class="actions"><a class="btn" href="<?=url('admin.php?section=knowledge-'.$kind.'&edit='.(int)$row['id'].'#knowledge-editor')?>">Sửa</a><form method="post" action="<?=url('admin.php?section=knowledge-'.$kind.'-toggle')?>"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$row['id']?>"><?php $statuses=array_keys($cfg['statuses']);$next=$row['status']===$statuses[0]?$statuses[1]:$statuses[0]; ?><input type="hidden" name="status" value="<?=e($next)?>"><button class="btn btn-outline"><?=e($cfg['statuses'][$next])?></button></form></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="4">Chưa có nội dung. Thêm bản ghi đầu tiên bên dưới.</td></tr><?php endif; ?></tbody></table></div>
    <form id="knowledge-editor" class="card admin-form" method="post" action="<?=url('admin.php?section=knowledge-'.$kind.'-save')?>"><h2><?=$editing?'Chỉnh sửa':'Thêm mới'?> · <?=e($cfg['label'])?></h2><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)($record['id']??0)?>">
    <?php foreach($cfg['fields'] as $field=>$meta): [$label,$rule,$required]=$meta;$value=$record[$field]??''; ?><div class="form-group"><label for="knowledge-<?=e($field)?>"><?=e($label)?><?=$required?' *':''?></label>
    <?php if(is_array($rule)): ?><select id="knowledge-<?=e($field)?>" name="<?=e($field)?>" required><?php foreach($rule as $key=>$label): ?><option value="<?=e($key)?>" <?=$value===$key?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select>
    <?php elseif($rule==='checkbox'): ?><input type="checkbox" id="knowledge-<?=e($field)?>" name="<?=e($field)?>" value="1" <?=$value?'checked':''?>>
    <?php elseif($rule==='integer'): ?><input class="input" type="number" id="knowledge-<?=e($field)?>" name="<?=e($field)?>" min="-100000" max="100000" step="1" value="<?=e($value)?>" required>
    <?php elseif(in_array($field,['content','summary','description'],true)): ?><textarea id="knowledge-<?=e($field)?>" name="<?=e($field)?>" rows="<?=$field==='content'?12:3?>" maxlength="<?=(int)$rule?>" <?=$required?'required':''?>><?=e($value)?></textarea>
    <?php else: ?><input class="input" type="<?=in_array($field,['source_url','target_url'],true)?'url':'text'?>" id="knowledge-<?=e($field)?>" name="<?=e($field)?>" maxlength="<?=(int)$rule?>" value="<?=e($value)?>" <?=$required?'required':''?>><?php endif; ?></div><?php endforeach; ?>
    <div class="form-group"><label for="knowledge-status">Trạng thái</label><select id="knowledge-status" name="status" required><?php foreach($cfg['statuses'] as $key=>$label): ?><option value="<?=e($key)?>" <?=($record['status']??'')===$key?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary">Lưu nội dung</button> <a class="btn btn-outline" href="<?=url('admin.php?section=knowledge-'.$kind)?>">Tạo bản ghi khác</a></form>
    <?php
}
