<?php 
use MapasCulturais\i;

$file = $entity->getFiles('evalmaster');
$url = $app->createUrl('opportunity', 'valuersmanagement', ['entity' => $entity->id]);
$template = '
<div id="file-{{id}}" class="objeto">
    <a href="{{url}}" rel="noopener noreferrer">{{name}}</a> 
    <a data-href="{{deleteUrl}}" data-target="#file-{{id}}" data-configm-message="Remover este arquivo?" class="buttons-rigth delete hltip js-remove-item" data-hltip-classes="hltip-ajuda" title="Excluir arquivo" style="float: right">Excluir</a>
    <a href="'.$url.'?file={{id}}" class="buttons-rigth btn btn-primary hltip" data-hltip-classes="hltip-ajuda" title="Clique para processar o arquivo enviado">processar</a>
</div>';

?>
<div class="clear" style="margin: 2em 0;">
    <h3><?php i::_e("Configurar avaliadores em lote"); ?>: </h3>
    <a class="add btn btn-default js-open-editbox hltip" data-target="#editbox-evalmaster-file" href="#"> <?= i::_e('Carregar arquivo para configuração de avaliadores em lote') ?></a>

    <div id="editbox-evalmaster-file" class="js-editbox mc-left" title="<?= i::_e('Carregar arquivo') ?>" data-submit-label="Enviar">
        <?php $this->ajaxUploader($entity, 'evalmaster', 'append', '.js-evalmaster', $template, '', false, false, false) ?>
    </div>

    <div class="js-evalmaster">
        <?php if ($file) : ?>
            <div id="file-<?php echo $file->id ?>" class="objeto <?php if ($this->isEditable()) echo i::_e(' is-editable'); ?>">
                <a href="<?php echo $file->url . '?id=' . $file->id; ?>" download><?php echo $file->description ? $file->description :  mb_substr(pathinfo($file->name, PATHINFO_FILENAME), 0, 20) . '.' . pathinfo($file->name, PATHINFO_EXTENSION); ?></a>
                <a data-href="<?php echo $file->deleteUrl ?>" data-target="#file-<?php echo $file->id ?>" data-configm-message="Remover este arquivo?" class="buttons-rigth delete hltip js-remove-item" data-hltip-classes="hltip-ajuda" title="Excluir arquivo." style="float: right"><?php i::_e("Excluir"); ?></a>
                <a href="<?=$url?>?file=<?=$file->id?>" class="buttons-rigth btn btn-primary hltip" data-hltip-classes="hltip-ajuda" title="Clique para processar o arquivo enviado"><?= i::_e('Processar') ?></a>
            </div>
        <?php endif ?>
    </div>
</div>