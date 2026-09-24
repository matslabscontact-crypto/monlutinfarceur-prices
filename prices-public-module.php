<?php
// Public-page parser only. Rejects ambiguous, stale or mismatched offers.
final class MLF_Amazon_Prices {
    const TTL=86400;
    static function parse_public($html, $asin, $now) {
        $reject=function ($m) { return new WP_Error('public_offer',$m); };
        if (!in_array($asin,array_column(mlf_catalog(),'asin'),true)) return $reject('Référence inconnue.');
        if (!class_exists('DOMDocument')) return $reject('Extension PHP DOM indisponible.');
        if (strlen($html)<100 || strlen($html)>4000000) return $reject('Page vide ou trop volumineuse.');
        if (preg_match('~captcha/validate|id=["\']captchacharacters|<title>\s*Robot Check~i',$html)) return $reject('Amazon demande une vérification. Aucun contournement.');
        $dom=new DOMDocument(); $previous=libxml_use_internal_errors(true);
        try { $loaded=$dom->loadHTML($html,LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (!$loaded) return $reject('HTML illisible.');
        $x=new DOMXPath($dom);
        foreach ($x->query('//script|//style') as $node) $node->parentNode->removeChild($node);
        $forms=$x->query('//*[@id="buybox"]//form[@id="addToCart"]');
        if ($forms->length!==1) {
            $title=$x->query('//title')->item(0);
            $body=$x->query('//body')->item(0);
            $hint=preg_replace('/\s+/u',' ',trim($body?$body->textContent:''));
            return $reject('Offre principale absente ou ambiguë. Page : '.($title?trim($title->textContent):'sans titre').' ('.strlen($html).' octets). '.substr($hint,0,500));
        }
        $form=$forms->item(0);
        $value=function ($name) use ($x,$form) {
            $nodes=$x->query('.//input[@name="'.$name.'"]',$form);
            return $nodes->length===1 ? $nodes->item(0)->getAttribute('value') : '';
        };
        if ($value('ASIN')!==$asin || $value('items[0.base][asin]')!==$asin) return $reject('La variante Amazon ne correspond pas au produit demandé.');
        $raw=$value('items[0.base][customerVisiblePrice][amount]');
        if ($value('items[0.base][customerVisiblePrice][currencyCode]')!=='EUR' || !preg_match('/^\d{1,5}\.\d{2}$/D',$raw) || (float)$raw<=0) return $reject('Prix en euros absent ou invalide.');
        $nodes=$x->query('.//*[@id="corePrice_feature_div"]//*[contains(concat(" ",normalize-space(@class)," ")," apex-pricetopay-value ")]/span[@class="a-offscreen"]',$form);
        if ($nodes->length!==1) return $reject('Prix principal affiché absent ou ambigu.');
        $visible=preg_replace('/[\s\x{00A0}\x{202F}]/u','',$nodes->item(0)->textContent);
        if ($visible!==number_format((float)$raw,2,',','').'€') return $reject('Le prix affiché et le prix de l’offre diffèrent.');
        $availability=$x->query('.//*[@id="availability"]//*[contains(@class,"primary-availability-message")]',$form);
        if ($availability->length!==1 || !preg_match('/^(En stock|Il ne reste plus que .*en stock)/ui',trim($availability->item(0)->textContent))) return $reject('Disponibilité non confirmée.');
        foreach ($x->query('.//*[contains(@id,"conditionInfo") or contains(@id,"ConditionInfo") or @id="renewedSingleOfferCaption_feature_div"]',$form) as $node) {
            if (preg_match('/occasion|reconditionn|renouvel|used|renewed/ui',$node->textContent)) return $reject('Offre d’occasion ou reconditionnée exclue.');
        }
        $area=$x->query('.//*[@id="corePrice_feature_div"]',$form)->item(0);
        if (preg_match('/exclusiv|réserv|abonn|prime|éclair/ui',$area->textContent)) return $reject('Prix conditionnel exclu.');
        return ['amount'=>(float)$raw,'currency'=>'EUR','display'=>number_format((float)$raw,2,',',' ').' €',
            'checked_at'=>$now,'expires_at'=>$now+self::TTL,'source'=>'public_page',
            'checked_label'=>(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i').' (Paris)'];
    }
}
