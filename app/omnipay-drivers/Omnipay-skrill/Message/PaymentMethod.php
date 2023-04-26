<?php

namespace Omnipay\Skrill\Message;

/**
 * Skrill Payment Method
 *
 * Codes required for applicable payment methods when using the Split Gateway.
 *
 * @author Joao Dias <joao.dias@cherrygroup.com>
 * @copyright 2013-2014 Cherry Ltd.
 * @license http://opensource.org/licenses/mit-license.php MIT
 * @version 6.5 Skrill Payment Gateway Integration Guide
 */
abstract class PaymentMethod
{
    /**
     * Skrill Direct
     */
    public const SKRILL_DIRECT               = 'MBD';

    /**
     * Skrill Digital Wallet
     */
    public const SKRILL_DIGITAL_WALLET       = 'WLT';


    // Credit/Debit Cards

    /**
     * All Card Types
     * Countries: All
     */
    public const ALL_CARD_TYPES              = 'ACC';

    /**
     * Visa
     * Countries: All
     */
    public const VISA                        = 'VSA';

    /**
     * MasterCard
     * Countries: All
     */
    public const MASTERCARD                  = 'MSC';

    /**
     * Visa Delta/Debit
     * Countries: United Kingdom
     */
    public const VISA_DELTA_DEBIT            = 'VSD';

    /**
     * Visa Electron
     * Countries: All
     */
    public const VISA_ELECTRON               = 'VSE';

    /**
     * Maestro
     * Countries: United Kingdom, Spain, Austria
     */
    public const MAESTRO                     = 'MAE';

    /**
     * American Express
     * Countries: All
     */
    public const AMERICAN_EXPRESS            = 'AMX';

    /**
     * Diners
     * Countries: All
     */
    public const DINERS                      = 'DIN';

    /**
     * JCB
     * Countries: All
     */
    public const JCB                         = 'JCB';

    /**
     * Laser
     * Countries: Rep. of Ireland
     */
    public const LASER                       = 'LSR';

    /**
     * Carte Bleue
     * Countries: France
     */
    public const CARTE_BLEUE                 = 'GCB';

    /**
     * Dankort
     * Countries: Denmark
     */
    public const DANKORT                     = 'DNK';

    /**
     * PostePay
     * Countries: Italy
     */
    public const POSTEPAY                    = 'PSP';

    /**
     * CartaSi
     * Countries: Italy
     */
    public const CARTASI                     = 'CSI';


    // Instant Banking Options

    /**
     * Skrill Direct (Online Bank Transfer)
     * Countries: Germany, United Kingdom, France, Italy, Spain, Hungary, Austria
     */
    public const ONLINE_BANK_TRANSFER        = 'OBT';

    /**
     * Giropay
     * Countries: Germany
     */
    public const GIROPAY                     = 'GIR';

    /**
     * Direct Debit / ELV
     * Countries: Germany
     */
    public const DIRECT_DEBIT_ELV            = 'DID';

    /**
     * Sofortüberweisung
     * Countries: Germany, Austria, Belgium, Netherlands, Switzerland, United Kingdom
     */
    public const SOFORTUEBERWEISUNG          = 'SFT';

    /**
     * eNETS
     * Countries: Singapore
     */
    public const ENETS                       = 'ENT';

    /**
     * Nordea Solo
     * Countries: Sweden
     */
    public const NORDEA_SOLO_SWE             = 'EBT';

    /**
     * Nordea Solo
     * Countries: Finland
     */
    public const NORDEA_SOLO_FIN             = 'SO2';

    /**
     * iDEAL
     * Countries: Netherlands
     */
    public const IDEAL                       = 'IDL';

    /**
     * EPS (Netpay)
     * Countries: Austria
     */
    public const EPS_NETPAY                  = 'NPY';

    /**
     * POLi
     * Countries: Australia
     */
    public const POLI                        = 'PLI';

    /**
     * All Polish Banks
     * Countries: Poland
     */
    public const ALL_POLISH_BANKS            = 'PWY';

    /**
     * ING Bank Śląski
     * Countries: Poland
     */
    public const ING_BANK_SLASKI             = 'PWY5';

    /**
     * PKO BP (PKO Inteligo)
     * Countries: Poland
     */
    public const PKO_BP_PKO_INTELIGO         = 'PWY6';

    /**
     * Multibank (Multitransfer)
     * Countries: Poland
     */
    public const MULTIBANK_MULTITRANSFER     = 'PWY7';

    /**
     * Lukas Bank
     * Countries: Poland
     */
    public const LUKAS_BANK                  = 'PWY14';

    /**
     * Bank BPH
     * Countries: Poland
     */
    public const BANK_BPH                    = 'PWY15';

    /**
     * InvestBank
     * Countries: Poland
     */
    public const INVEST_BANK                 = 'PWY17';

    /**
     * PeKaO S.A.
     * Countries: Poland
     */
    public const PEKAO_SA                    = 'PWY18';

    /**
     * Citibank handlowy
     * Countries: Poland
     */
    public const CITIBANK_HANDLOWY           = 'PWY19';

    /**
     * Bank Zachodni WBK (Przelew24)
     * Countries: Poland
     */
    public const BANK_ZACHODNI_WBK_PRZELEW24 = 'PWY20';

    /**
     * BGŻ
     * Countries: Poland
     */
    public const BGZ                         = 'PWY21';

    /**
     * Millenium
     * Countries: Poland
     */
    public const MILLENIUM                   = 'PWY22';

    /**
     * mBank (mTransfer)
     * Countries: Poland
     */
    public const MBANK_MTRANSFER             = 'PWY25';

    /**
     * Płacę z Inteligo
     * Countries: Poland
     */
    public const PLACE_Z_INTELIGO            = 'PWY26';

    /**
     * Bank Ochrony Środowiska
     * Countries: Poland
     */
    public const BANK_OCHRONY_SRODOWISKA     = 'PWY28';

    /**
     * Nordea
     * Countries: Poland
     */
    public const NORDEA                      = 'PWY32';

    /**
     * Fortis Bank
     * Countries: Poland
     */
    public const FORTIS_BANK                 = 'PWY33';

    /**
     * Deutsche Bank PBC S.A.
     * Countries: Poland
     */
    public const DEUTSCHE_BANK_PBC_SA        = 'PWY36';

    /**
     * ePay.bg
     * Countries: Bulgaria
     */
    public const EPAY_BG                     = 'EPY';
}
