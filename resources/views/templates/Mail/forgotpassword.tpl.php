<?php

use Continy\Core\Config;
use Continy\Core\Language;
use Continy\Plugin\EMSLLanguage;

?>
<zone:body>
  <table width="100%" cellspacing="0" cellpadding="0">
    <tbody>
      <tr>
        <td>
          <table width="600" align="center" cellpadding="0" cellspacing="0">
            <!-- Main Wrapper Table with initial width set to 60opx -->
            <tbody>
              <tr>
                <!-- HTML Spacer row -->
                <td style="font-size: 0; line-height: 0;" height="20">
                  <table width="96%" align="left" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size: 0; line-height: 0;" height="20">&nbsp;</td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <!-- HTML Spacer row -->
                <td style="font-size: 0; line-height: 0;" height="20">
                  <table width="96%" align="left" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size: 0; line-height: 0;" height="20">&nbsp;</td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <!-- Introduction area -->
                <td>
                  <table width="96%" align="left" cellpadding="0" cellspacing="0">
                    <tr>
                      <!-- row container for TITLE/EMAIL THEME -->
                      <td align="center" style="font-size: 32px; font-weight: 300; line-height: 2.5em; color: #929292; font-family: sans-serif;">
                        <?= Language::get('login/forgotmail/title') ?>
                      </td>
                    </tr>
                    <tr>
                      <td style="font-size: 0; line-height: 0;" height="20">
                        <table width="96%" align="left" cellpadding="0" cellspacing="0">
                          <tr>
                            <!-- HTML Spacer row -->
                            <td style="font-size: 0; line-height: 0;" height="20">&nbsp;</td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                    <tr>
                      <!-- Row container for Intro/ Description -->
                      <td align="left" style="font-size: 14px; font-style: normal; font-weight: 100; color: #929292; line-height: 1.8; text-align:justify; padding:10px 20px 0px 20px; font-family: sans-serif;">
                        <p><?= Language::get('login/forgotmail/description') ?></p>
                        <p><a href="<?= Config::get('app/url') ?>/login/reset?token=<?= $reset->token ?>&email=<?= $reset->email; ?>"><?= Language::get('login/forgotmail/clickThisLink'); ?></a></p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <!-- HTML Spacer row -->
                <td style="font-size: 0; line-height: 0;" height="10">
                  <table width="96%" align="left" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size: 0; line-height: 0;" height="20">&nbsp;</td>
                    </tr>
                  </table>
                </td>
              </tr>

              <tr>
                <!-- HTML spacer row -->
                <td style="font-size: 0; line-height: 0;" height="20">
                  <table width="96%" align="left" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="font-size: 0; line-height: 0;" height="20">&nbsp;</td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr bgcolor="#d0cfcf">
                <td>
                  <table class="footer" width="48%" align="left" cellpadding="0" cellspacing="0">
                    <!-- First column of footer content -->
                    <tr>
                      <td>
                        <p align="center" style="font-size: 22px; font-weight:300; line-height: 2.5em; color: #929292; font-family: sans-serif;">
                          <?= EMSLLanguage::replace(Language::get('login/forgotmail/companyName')); ?>
                        </p>
                      </td>
                    </tr>
                  </table>
                  <table class="footer" width="48%" align="left" cellpadding="0" cellspacing="0">
                    <!-- Second column of footer content -->
                    <tr>
                      <td>
                        <p style="font-size: 14px; font-style: normal; font-weight:normal; color: #ffffff; line-height: 1.8; text-align:left;padding-top:10px; margin-left:20px; margin-right:20px; font-family: sans-serif;">
                          <?= EMSLLanguage::replace(Language::get('login/forgotmail/companyAddress')); ?>
                        </p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>
    </tbody>
  </table>
</zone:body>