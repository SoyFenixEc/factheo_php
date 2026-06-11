package com.quijotelui.firmador;

import java.io.File;
import java.util.Scanner;
import java.io.Console;

/**
 *
 * @author jorgequiguango
 */
public class Main {

    /**
     * @param args the command line arguments
     */
    public static void main(String[] args) {
        

        System.out.println("GREJDESARROLLO - Basado en QuijoteLui Firmador - "+System.getProperty("user.dir"));
        XAdESBESSignature xadesBesFirma = new XAdESBESSignature();
        
        //File archivo = new File("C:\\xampp\\htdocs\\facturar\\comprobantes\\generados\\"+ "factura_78036.xml");
        File archivo = new File(args[1]+args[0]);
        //String urlOutArchivo = "C:\\xampp\\htdocs\\facturar\\comprobantes\\firmados";
        String urlOutArchivo = args[2];
        //String PKCS12_RESOURCE = "C:\\xampp\\htdocs\\facturar\\librerias";
        String PKCS12_RESOURCE = args[3];
        String PKCS12_PASSWORD;

        //String nombreP12 = "101858546024539438259071211.p12";
        String nombreP12 = args[4];
        PKCS12_RESOURCE = PKCS12_RESOURCE + File.separatorChar + nombreP12;
        System.out.println("Archivo Firma P12: " + PKCS12_RESOURCE);
        
        PKCS12_PASSWORD = new String(args[5]);
        /*
        Para firmar con un certificado emitido por le BCE
         */
        if("BC".equals(args[6]))
        {
        xadesBesFirma.sign(archivo,
                urlOutArchivo,
                PKCS12_RESOURCE,
                PKCS12_PASSWORD,
                TokensAvailables.BCE_IKEY2032);
        }

        /*
        Para firmar con un certificado emitido por le Security Data
         */
        if("SD".equals(args[6]))
        {
        xadesBesFirma.sign(archivo,
                urlOutArchivo,
                PKCS12_RESOURCE,
                PKCS12_PASSWORD,
                TokensAvailables.SD_EPASS3000);
        }
        
        /*
        Para firmar con un certificado emitido por el ANF
         */
        if("ANF".equals(args[6]))
        {
        xadesBesFirma.sign(archivo,
                urlOutArchivo,
                PKCS12_RESOURCE,
                PKCS12_PASSWORD,
                TokensAvailables.ANF1);
        }
        
        /*
        Para firmar con un certificado emitido por el Consejo de la Judicatura
         */
        if("CJ".equals(args[6]))
        {
        xadesBesFirma.sign(archivo,
                urlOutArchivo,
                PKCS12_RESOURCE,
                PKCS12_PASSWORD,
                TokensAvailables.KEY4_CONSEJO_JUDICATURA);
        }
        /*
        Para firmar con un certificado emitido por el Consejo de la Judicatura
         */
        if("GEN".equals(args[6]))
        {
        xadesBesFirma.sign(archivo,
                urlOutArchivo,
                PKCS12_RESOURCE,
                PKCS12_PASSWORD,
                TokensAvailables.GENERAL);
        }
    }
}
