<?php 
use MapasCulturais\i;

$file = $entity->getFiles('evalmaster');
$url = $app->createUrl('opportunity', 'valuersmanagement', ['entity' => $entity->id]);
$template = '
<div id="file-{{id}}" class="objeto">
    <a href="{{url}}" rel="noopener noreferrer">{{name}}</a> 
    <a href="'.$url.'?file={{id}}" class="btn btn-primary hltip js-dataprev-process" data-hltip-classes="hltip-ajuda" title="Clique para processar o arquivo enviado">processar</a>
    <a data-href="{{deleteUrl}}" data-target="#file-{{id}}" data-configm-message="Remover este arquivo?" class="buttons-rigth delete hltip js-remove-item" data-hltip-classes="hltip-ajuda" title="Excluir arquivo"><span class="configdelete">Excluir</span></a>
</div>';

?>
<span class="label"><?php i::_e("Carregar avaliadores em lote"); ?>: </span><br>
<a class="add btn btn-default js-open-editbox hltip" data-target="#editbox-evalmaster-file" href="#"> <?= i::_e('Carregar aqruivo em lote de avaliadores') ?></a>

<div id="editbox-evalmaster-file" class="js-editbox mc-left" title="<?= i::_e('Carregar arquivo') ?>" data-submit-label="Enviar">
    <?php $this->ajaxUploader($entity, 'evalmaster', 'append', '.js-evalmaster', $template, '', false, false, false) ?>
</div>

<div class="js-evalmaster">
    <?php if ($file) : ?>
        <div id="file-<?php echo $file->id ?>" class="objeto <?php if ($this->isEditable()) echo i::_e(' is-editable'); ?>">
            <a href="<?php echo $file->url . '?id=' . $file->id; ?>" download><?php echo $file->description ? $file->description :  mb_substr(pathinfo($file->name, PATHINFO_FILENAME), 0, 20) . '.' . pathinfo($file->name, PATHINFO_EXTENSION); ?></a>
            <a data-href="<?php echo $file->deleteUrl ?>" data-target="#file-<?php echo $file->id ?>" data-configm-message="Remover este arquivo?" class="buttons-rigth delete hltip js-remove-item" data-hltip-classes="hltip-ajuda" title="Excluir arquivo."><span class="configdelete"><?php i::_e("Excluir"); ?></span></a>
            <a href="<?=$url?>?file=<?=$file->id?>" class="buttons-rigth delete hltip" data-hltip-classes="hltip-ajuda" title="Clique para processar o arquivo enviado"><?= i::_e('Processar') ?></a>
        </div>
    <?php endif ?>
</div>