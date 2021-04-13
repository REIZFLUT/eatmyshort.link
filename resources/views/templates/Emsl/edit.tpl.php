<?php

use Continy\Core\Notification;
use Continy\Core\Html;
use Continy\Core\Language;
use App\Models\EMSLConfig;

?>

<?= view_template('Emsl/mainnavi'); ?>

<zone:body>
    <main id="shortlink">
    <?= Html::formPostStart('/shortlink/edit/'.$shortlink->id) ?>
        <div id="editShortlink" class="">
        
        <div class="form-input">
                    <?= Html::input(
                        'text',
                        'from_path',
                        '',
                        ['required' => 'required', 'pattern' => '[a-z0-9_-]+', 'placeholder' => EMSLConfig::get('shortDomain') . '/'],
                    ) ?>
                </div>
                <div class="form-input">
                    <?= Html::input('url', 'to_url', '', ['required' => 'required'], 'emsl') ?>
                </div>
                <div class="form-input">
                    <?= Html::submitBtn('create', [], 'emsl') ?>
                </div>

        
        </div>
        <div id="editLocalisations">
        
        <button type="button" class="btn">Add Localisation</button>
        
        <div class="localisation">
            <div class="form-input">
              <select name="language">
                <?= Html::OptionsFromList(Language::getAllLngCodes()); ?>
              </select>
            </div>
            <div class="form-input">
            <?= Html::input('url', 'to_url', '', ['required' => 'required'], 'emsl') ?>
            </div>
        </div>
        
        </div>

        <?= Html::formEnd() ?>
    </main>
</zone:body>