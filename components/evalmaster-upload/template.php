<?php

use MapasCulturais\i;

/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */
$this->import("
    entity-file
    mc-modal
");
?>

<div class="valuers-management">
    <mc-modal :title="modalTitle">
        <template v-if="!loading && !hasFile" #default>
            <p><?php i::_e('Faça upload da planilha de distribuição preechida') ?></p>

            <div class="valuers-management__field">
                <div>
                    <label for="fileUpload">
                        <small class="input-label semibold">
                            <p>{{fileName}}</p>
                            <mc-icon name="add"></mc-icon>
                        </small>
                    </label>
                </div>
                <div class="field">
                    <input type="file" name="file" id="fileUpload" @change="setFile" ref="file">
                </div>
            </div>
        </template>

        <template v-if="loading" #default>
            <p class="semibold">
                <?= i::__("Carregando") ?> <mc-icon name="loading"></mc-icon>
            </p>
        </template>

        <template #button="modal">
            <button class="button button--primary-outline button--large" @click="modal.open()"><?php i::_e('Importar distribuição de avaliações') ?></button>
        </template>

        <template #actions="modal">
            <div v-if="hasFile">
                <entity-file :entity="entity" groupName="evalmaster" title="" editable :required="false">
                    <template  #button>
                        <div class="valuers-management__process">
                            <div class="process__title">
                                <?php i::_e('Arquivo') ?> <strong><i>{{entityFile?.name}}</i></strong> <?php i::_e('pendente de processamento') ?>
                            </div>
                            <div class="process__action">
                                <div>
                                    <a @click="deleteFile()" class="button button--text button--large button--md">
                                        <mc-icon name="delete"></mc-icon> <?php i::_e("Deletar") ?>
                                    </a>
                                </div>

                                <div>
                                    <a @click="processFile()" class="button button--primary button--large button--md">
                                        <mc-icon name="process"></mc-icon> <?php i::_e("Processar") ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </template>
                </entity-file>
            </div>
            <div v-if="!hasFile">
                <div class="col-6">
                    <button class="button button--text button--large button--md" @click="cancel(modal)"><?php i::_e('Cancelar') ?></button>
                </div>
                <div class="col-6">
                    <button class="button button--primary button--large button--md" @click="upload(modal)"><?php i::_e('Enviar') ?></button>
                </div>
            </div>
        </template>
    </mc-modal>
</div>