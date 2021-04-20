<?php

use Continy\Core\Notification;
use Continy\Core\Html;
use Continy\Core\Language;
use App\Models\EMSLConfig;

?>

<?= view_template('Emsl/mainnavi'); ?>

<zone:body>
    <main id="shortlink">
    <div id="mainNewShortlink" class="edit">
            <div class="container">
                <?= Html::formPostStart('/shortlink/create') ?>
                <div class="form-input">
                    <?= Html::input(
                        'text',
                        'from_path',
                        $shortlink->from_path,
                        ['required' => 'required', 'pattern' => '[a-z0-9_-]+', 'placeholder' => EMSLConfig::get('shortDomain') . '/', 'readonly' => 'readonly'],
                    ) ?>
                </div>
                <div class="form-input">
                    <?= Html::input('url', 'to_url', $shortlink->to_url, ['required' => 'required'], 'emsl') ?>
                </div>             
                <div class="form-input">
                    <?= Html::submitBtn('save', ['class' => 'btn btn-block btn-primary'], 'emsl') ?>
                </div>
                <div class="form-input">
                    <button type="button" class="btn btn-block"><?= Language::get('emsl/button/addLocal'); ?></button>
                </div>      
                <div class="form-input">
                    <button type="button" class="btn btn-block btn-error"><?= Language::get('emsl/button/delete'); ?></button>
                </div>                                 
                <?= Html::formEnd() ?>
            </div>
        </div>


    <?= Html::formPostStart('/shortlink/edit/'.$shortlink->id) ?>
        
        
        <div id="editLocalisations" class="container">        
        <?php foreach($shortlink->getLocals()->getResult() as $local): ?>
        <div class="localisation">
        <?= Html::input('hidden', 'id', $local->id); ?>
        <div class="input-wrapper">
              <select name="lng[]" required>
                <?= Html::OptionsFromList($availableLangs, $local->lng); ?>
              </select>
            </div>
            <div class="input-wrapper">
            <?= Html::input('url', 'to_url[]', $local->to_url, ['required' => 'required'], 'emsl') ?>
            </div>
            <div class="">
                <a href="#" class="btn btn-error">Löschen</a>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <?= Html::formEnd() ?>
    </main>
</zone:body>