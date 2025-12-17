[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2Fbring-symcon%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)]()
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2Fbring-symcon%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v80-v81-q3-2025/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/bring-symcon/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/CCast/actions)
[![Run Tests](https://github.com/Nall-chan/bring-symcon/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/CCast/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#3-spenden)
[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#3-spenden)  

# Bring-Symcon  <!-- omit in toc -->

Einbinden von `Bring!` Einkauflisten in IPS.  


## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Voraussetzungen](#1-voraussetzungen)
- [2. Software-Installation](#2-software-installation)
- [3. Enthaltende Module](#3-enthaltende-module)
- [4. Anhang](#4-anhang)
	- [1. GUID der Module](#1-guid-der-module)
	- [2. Changelog](#2-changelog)
	- [3. Spenden](#3-spenden)
- [5. Lizenz](#5-lizenz)

----------

## 1. Voraussetzungen

- IP-Symcon ab Version 8.1
- Account bei Bring!

## 2. Software-Installation
  
Über den 'Module-Store' in IPS das Modul `Bring!` hinzufügen.  
**Bei kommerzieller Nutzung (z.B. als Errichter oder Integrator) wenden Sie sich bitte an den Autor.**  

![Module-Store](imgs/install.png)  

Nach der Installation über den Store, wird abgefragt ob ein ([Konfigurator](Bring%20Configurator/README.md)) von diesem Modul automatisch angelegt werden soll.  

![Module-Store](imgs/install2.png)  
![Module-Store](imgs/install3.png)  

Dadurch wird automatisch die benötigte [Bring! Account-Instanz (IO)](Bring%20Account/README.md) erstellt.  

![Module-Store](imgs/install4.png)  

Die weitere Konfiguration ist in der [Konfigurator-Instanz](Bring%20Configurator/README.md#4-einrichten-der-instanzen-in-ip-symcon) beschrieben.  

Allgemeine Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

## 3. Enthaltende Module

- **Bring Konto** ([Dokumentation](Bring%20Account/README.md))  
  IO-Modul für einen Bring! Konto.  

- **Bring Konfigurator** ([Dokumentation](Bring%20Configurator/README.md))  
  Konfigurator um alle Listen eines Accounts in Symcon anzulegen.  

- **Bring List** ([Dokumentation](Bring%20List/README.md))  
  Eine Einkaufliste des Accounts als Instanz in Symcon.  

## 4. Anhang

### 1. GUID der Module

| Modul               | Typ          | Prefix |                  GUID                  |
| :------------------ | :----------- | :----: | :------------------------------------: |
| Bring! Account      | IO           | BRING  | {C6D2590B-D9DB-113F-5EF1-9323E7B9DBDA} |
| Bring! Konfigurator | Configurator | BRING  | {8D4CE681-3632-57A4-AE9D-7265B716B913} |
| Bring! List         | Gerät        | BRING  | {44D63530-0E14-8B8F-3E1A-A79728240524} |

----------

### 2. Changelog

**Version 1.20:**  

- Version für Symcon 8.1 und neuer  
- Durchgängige Nutzung von Darstellungen anstatt von Profilen  

**Version 1.10:**  

- Details konnten im WebFront nicht bearbeitet werden  

**Version 1.00:**  

- Beta Release für Symcon 7.1  

----------

### 3. Spenden  
  
Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)  

[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share) 

## 5. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
