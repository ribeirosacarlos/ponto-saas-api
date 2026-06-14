# Changelog

## [2.2.2](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v2.2.1...v2.2.2) (2026-06-14)


### Correções de Bugs

* considera feriados no fechamento de ponto e exibe como entrada n… ([38d9f31](https://github.com/ribeirosacarlos/ponto-saas-api/commit/38d9f31391781b1a90b3e950357e7533fe8684d0))
* considera feriados no fechamento de ponto e exibe como entrada na listagem ([18ab850](https://github.com/ribeirosacarlos/ponto-saas-api/commit/18ab85093740bbc25277f559cfe795ca9df0631a))

## [2.2.1](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v2.2.0...v2.2.1) (2026-06-14)


### Correções de Bugs

* reinterpreta clocked_at no timezone da empresa fora de requests HTTP ([bb1b84e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/bb1b84ecd2b2a6a3dab5b2eee3b3445c9770d350))

## [2.2.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v2.1.0...v2.2.0) (2026-06-11)


### Novas Funcionalidades

* add pt/en translations for the Spain time-tracking blog post ([ef1fa94](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ef1fa947dada307b955ad2b77ce99105a131c2e2))

## [2.1.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v2.0.2...v2.1.0) (2026-06-11)


### Novas Funcionalidades

* add seeder for Spanish blog posts about time tracking compliance ([840998f](https://github.com/ribeirosacarlos/ponto-saas-api/commit/840998f42f973c9259c827ffe266e5375b9358ef))
* public leads endpoint for blog lead magnet capture ([54e39e8](https://github.com/ribeirosacarlos/ponto-saas-api/commit/54e39e8e6d2276e95f97ce099f33ba6fae627157))
* refine public blog API contract and add blog sitemap ([4220f7e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4220f7e70cfa4739545b6addd3dedcb7b1f6b43f))

## [2.0.2](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v2.0.1...v2.0.2) (2026-06-10)


### Correções de Bugs

* derive hourly absence allowance minutes from time entries only ([06e30ae](https://github.com/ribeirosacarlos/ponto-saas-api/commit/06e30aea50c862d2c4b9443032ae1daf18455dbb))

## [2.0.1](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v2.0.0...v2.0.1) (2026-06-09)


### Correções de Bugs

* permitir fechamento mensal para qualquer role da empresa ([21643a4](https://github.com/ribeirosacarlos/ponto-saas-api/commit/21643a481afce16cf42eba08a6a6f366be8bd609))
* permitir fechamento mensal para qualquer role da empresa ([343ba3d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/343ba3d6ac3ab22a2ce39bbae11f57d9f014cd47))

## [2.0.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.10.0...v2.0.0) (2026-06-09)


### ⚠ BREAKING CHANGES

* `employee_id` is now required on POST /api/admin/monthly-closures

### Novas Funcionalidades

* monthly closure now scoped per employee instead of per company ([da6b78b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/da6b78bfbac4d244cda9b08d723c2674c9707d81))

## [1.10.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.9.0...v1.10.0) (2026-06-09)


### Novas Funcionalidades

* delete absence allowances ([40d2c95](https://github.com/ribeirosacarlos/ponto-saas-api/commit/40d2c950f68bbbbb5441ad9357829b60b9d8834a))
* delete absence allowances ([b119ddc](https://github.com/ribeirosacarlos/ponto-saas-api/commit/b119ddce87710293e32f3abc74f0c9b12ee6fa87))

## [1.9.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.8.7...v1.9.0) (2026-06-09)


### Novas Funcionalidades

* persist absence time entries ([2f82398](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2f82398d2704961a98aa85206518fd0ca56c460c))
* persist absence time entries ([f476645](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f476645356b1e9b14dab3583d92f2962a3a26984))

## [1.8.7](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.8.6...v1.8.7) (2026-06-08)


### Correções de Bugs

* enforce tenant isolation for timesheet closures ([d60d693](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d60d69383f64e0c284fd5b49487ae32cde90ab16))

## [1.8.6](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.8.5...v1.8.6) (2026-06-08)


### Correções de Bugs

* Refactor TimeEntryController to include absence handling ([771b4be](https://github.com/ribeirosacarlos/ponto-saas-api/commit/771b4be72badd8bd683c6cf5615324e6bccee30d))
* Refactor TimeEntryController to include absence handling ([7d8b4f8](https://github.com/ribeirosacarlos/ponto-saas-api/commit/7d8b4f8f87205c08d6e447ab0bb9e76d31a6c164))

## [1.8.5](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.8.4...v1.8.5) (2026-06-08)


### Correções de Bugs

* Enhance TimeEntryController with virtual absence logic ([acea732](https://github.com/ribeirosacarlos/ponto-saas-api/commit/acea732e03b4c021b29d71c9ddf3a9584ecc5e7a))
* Enhance TimeEntryController with virtual absence logic ([363bed3](https://github.com/ribeirosacarlos/ponto-saas-api/commit/363bed32981c81d023445b8d0b0037fcac31236c))
* Refactor TimeEntryController to include virtual absence handling ([a70bf66](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a70bf6607aff3e2bf8a33afffef2b28970e46ea4))

## [1.8.4](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.8.3...v1.8.4) (2026-06-08)


### Correções de Bugs

* Clarify absence status impact on point calculation ([d523527](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d5235276a8a1e27cdbff251c3cfe71a695d95b9f))
* Enhance TimeEntryController for employee visibility ([5e8d996](https://github.com/ribeirosacarlos/ponto-saas-api/commit/5e8d99622bbb043a22c3b7de2166e6c72f664e25))
* Improve absence calculation logic and remove debug logs ([b578ecc](https://github.com/ribeirosacarlos/ponto-saas-api/commit/b578eccd3b49a5c1265ba75454226016466f5f3b))
* Update time-entry API documentation for worked minutes ([9473b8f](https://github.com/ribeirosacarlos/ponto-saas-api/commit/9473b8feb24b4c92838b8b4df1daf0cf58771cea))

## [1.8.3](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.8.2...v1.8.3) (2026-06-08)


### Correções de Bugs

* fix:  ([312fbdc](https://github.com/ribeirosacarlos/ponto-saas-api/commit/312fbdcb7a1d954bb40dc65932004b69c322b34b))
* Add TYPE_EXCUSED_ABSENCE constant to Absence model ([c387f28](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c387f28afad530010f9b7810cd69d4df0ceb932f))
* Update absence request validation rules ([efaa1b7](https://github.com/ribeirosacarlos/ponto-saas-api/commit/efaa1b7cefaaaa82fbd4449e613f0ae16efccef4))
* Use AbsenceAllowanceService for absence creation ([7959902](https://github.com/ribeirosacarlos/ponto-saas-api/commit/79599022f61c2b84512d6807cf323b05cb99f37b))

## [1.8.2](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.8.1...v1.8.2) (2026-06-08)


### Correções de Bugs

* Add check for finalized summaries in calculation ([1ebdfcc](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1ebdfcc96578a0a6f9336d2aec2f10d8a2f3a4a8))
* Add test for current day exclusion in overtime totals ([bfeb7d1](https://github.com/ribeirosacarlos/ponto-saas-api/commit/bfeb7d12cdca8a6afdf4d3f1c876324a7baa0803))

## [1.8.1](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.8.0...v1.8.1) (2026-06-08)


### Correções de Bugs

* fix:  ([f4219da](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f4219dab9875b39093f460ca6ec72cb44cc56de0))
* formatting of PHPDoc summary in TimesheetCalculationServicefix ([a2ff5ce](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a2ff5ceb60035b17b4e8ac2083758a3d96b38747))
* Rename test method and update assertions for balance ([a63f609](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a63f609eb08d51b92e462efb4565a368a5095c9b))

## [1.8.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.7.4...v1.8.0) (2026-06-07)


### Novas Funcionalidades

* add medical certificate workflow ([7c0a40d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/7c0a40d4026994e66faa1ff0fc4cd991437ee3e6))
* add medical certificate workflow ([3517b94](https://github.com/ribeirosacarlos/ponto-saas-api/commit/3517b94e04138436c234972e9db5fbaa9f202678))

## [1.7.4](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.7.3...v1.7.4) (2026-06-06)


### Correções de Bugs

* count closed pairs when session remains open ([51da83a](https://github.com/ribeirosacarlos/ponto-saas-api/commit/51da83aea6851d7819f7351769ab090f4f84087a))

## [1.7.3](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.7.2...v1.7.3) (2026-06-06)


### Correções de Bugs

* correct time entry adjustment sequence ([655d1e5](https://github.com/ribeirosacarlos/ponto-saas-api/commit/655d1e5153e4d2eb2916f64d03945a5f62679aeb))
* correct time entry adjustment sequence ([5436251](https://github.com/ribeirosacarlos/ponto-saas-api/commit/543625136456b1c4c982e776bbb9fc604f8392ac))

## [1.7.2](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.7.1...v1.7.2) (2026-06-05)


### Correções de Bugs

* exclude rejected team entries ([631eeee](https://github.com/ribeirosacarlos/ponto-saas-api/commit/631eeeeb09228b5c9f19c7d7660bd07819285d7b))
* exclude rejected team entries ([9f72e34](https://github.com/ribeirosacarlos/ponto-saas-api/commit/9f72e34e9ab6efc7b884f57e516546b86e62c129))
* keep worked minutes as real worked time ([04d3d35](https://github.com/ribeirosacarlos/ponto-saas-api/commit/04d3d358cb07236442fe3343fcca9cfa47a8cb5e))
* keep worked minutes as real worked time ([11941cb](https://github.com/ribeirosacarlos/ponto-saas-api/commit/11941cb3cabc3365beb54fd1e10539ffcd10821e))

## [1.7.1](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.7.0...v1.7.1) (2026-06-05)


### Correções de Bugs

* remove document storage conflict markers ([3ee96b4](https://github.com/ribeirosacarlos/ponto-saas-api/commit/3ee96b48e9e6fdf4510cad2c134610f9cee6251a))
* remove document storage conflict markers ([ede2859](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ede285944da7fc7602d040361744f00bb9b15f30))

## [1.7.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.6.0...v1.7.0) (2026-06-05)


### Novas Funcionalidades

* separate document uploads by company prefix ([4096e05](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4096e05de5d43089292160762fd8344ff8698b64))
* separate document uploads by company prefix ([4004694](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4004694ec97bd1df1ddd711448bc44a18dbd5c48))

## [1.6.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.5.3...v1.6.0) (2026-06-05)


### Novas Funcionalidades

* separate document uploads by company prefix ([13bcd96](https://github.com/ribeirosacarlos/ponto-saas-api/commit/13bcd96a45721c3684f71a00a660f0dfb02c9b36))
* separate document uploads by company prefix ([e9a72b7](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e9a72b7b299389026d16d91ad19d2fa7858f4ee9))

## [1.5.3](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.5.2...v1.5.3) (2026-06-04)


### Correções de Bugs

* block deleting assigned shifts ([35a6dc6](https://github.com/ribeirosacarlos/ponto-saas-api/commit/35a6dc62c78e1963a940d031e27c873eda14affe))
* block deleting assigned shifts ([cc2278c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/cc2278c4f7c7d62f84095436a2b295ffa492f38b))

## [1.5.2](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.5.1...v1.5.2) (2026-06-03)


### Correções de Bugs

* report worked hours as net of break overage ([b542f2e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/b542f2ea3e01fdcec6630c085cfbb37dc98cd65d))
* report worked hours as net of break overage ([a920d25](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a920d25c4e1953ba51442a08fa187a38b0738900))

## [1.5.1](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.5.0...v1.5.1) (2026-06-02)


### Correções de Bugs

* discount exceeded break from worked balance ([ecae444](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ecae444b1b68ad16cd372bcb1fca2caf9635113d))
* keep break time out of worked hours ([517d90e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/517d90e18001e0c89acab548ca3a9ca6fb913766))

## [1.5.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.4.0...v1.5.0) (2026-05-31)


### Novas Funcionalidades

* align billing backend with BACKEND_BILLING_SETUP.md spec ([2fbb061](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2fbb061fe52ecef7460498b37bf0495d25e01218))
* align billing backend with BACKEND_BILLING_SETUP.md spec ([977b2b6](https://github.com/ribeirosacarlos/ponto-saas-api/commit/977b2b6c966bdb6a313e8e978c556e4a7c90448d))

## [1.4.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.3.1...v1.4.0) (2026-05-25)


### Novas Funcionalidades

* use employee name, month and year as PDF filename on download ([16cbfd7](https://github.com/ribeirosacarlos/ponto-saas-api/commit/16cbfd7fe12c5962f99d3156c552ee013d81eec5))
* use employee name, month and year as PDF filename on download ([3580920](https://github.com/ribeirosacarlos/ponto-saas-api/commit/358092026758aaae0695152b570176bd12d83d49))

## [1.3.1](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.3.0...v1.3.1) (2026-05-24)


### Correções de Bugs

* soft delete employees to preserve historical records ([c7f8ff1](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c7f8ff11f719b16d6fad13c78b6a964a98b6c27b))
* soft delete employees to preserve historical records ([683b48c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/683b48c12494af96e12ca01c2f24d5cfca9dc895))

## [1.3.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.2.0...v1.3.0) (2026-05-24)


### Novas Funcionalidades

* add user profile and company settings endpoints ([1c4f00d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1c4f00df8e7ae824fc455acca48c149b9939f35c))
* add user profile and company settings endpoints ([d327454](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d327454e98c4cf47212e649ea93905bec7df79e5))

## [1.2.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.1.1...v1.2.0) (2026-05-24)


### Novas Funcionalidades

* cover dashboard plan breakdown ([8ada4ea](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8ada4ea12ed74e4ec63daf38e29bdef36c31fbb1))
* cover dashboard plan breakdown ([47e9632](https://github.com/ribeirosacarlos/ponto-saas-api/commit/47e963207c5f12bbfc7738d4a984ed592486f065))

## [1.1.1](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.1.0...v1.1.1) (2026-05-23)


### Correções de Bugs

* trigger release workflow ([868c18d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/868c18de77a60d0253fe34c5f081beb660eb9af5))
* trigger release workflow ([ececd82](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ececd820ef8fe1c734334c8852b86c86d36a3cb5))

## [1.1.0](https://github.com/ribeirosacarlos/ponto-saas-api/compare/v1.0.0...v1.1.0) (2026-05-23)


### Novas Funcionalidades

* feat:  ([4219788](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4219788f8c8c211c15efca1242ba955dbce19521))
* adc email na url de invate ([f78cdcb](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f78cdcb61e8fb1ebe3f3f8ff8c988cf94eabb7d4))
* adc log de webhooks ([f23299c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f23299cefc8b663dbd0b85d0de95909d83177f56))
* ajustando a hora extra ([9e75b96](https://github.com/ribeirosacarlos/ponto-saas-api/commit/9e75b96d8fa79bcf79800349ccee0c2c4611ef1f))
* ajustando arquivos para rodar swagger ([8945b50](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8945b5050dbe74b9dbe42a2fc5f9d12efe48aae7))
* ajustando as regras de trail e plan ([e675970](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e675970e6faad00de12cd028dac5c93059ed4ba2))
* ajustando solicitação de ponto ([c08f5b4](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c08f5b488592ec230618cda127b069b58a5f4435))
* ajuste cors ([1d48d87](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1d48d876ad338b4fd78c9bce2610b8619b49db07))
* ajuste deploy para rodar migrate ([ebc0171](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ebc0171fefd761ed95c3e71bcac2791726c53ae6))
* ajuste na rota ([e5afa1e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e5afa1ef948f5208c38e86208f33b5e91183ff7c))
* ajuste na rota ([46ca2d6](https://github.com/ribeirosacarlos/ponto-saas-api/commit/46ca2d6001363a36c1464e845c2ce84b7b18a4e4))
* ajuste no swagger ([0b1f42c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/0b1f42c3e23e3a4e87f7b98a6766159c4801d3c3))
* ajuste no swagger ([adce50b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/adce50b0caa48be6c13804002101bb29f83a2dfc))
* ajuste no workedtoday ([dfd0c83](https://github.com/ribeirosacarlos/ponto-saas-api/commit/dfd0c833c3e62c7c21d81901c637c0fa3a79065e))
* ajuste no workedtoday ([952b965](https://github.com/ribeirosacarlos/ponto-saas-api/commit/952b96505119d3bcdfb4bab4932dce363f14822d))
* ajuste permissão geolocalização ([aeb1f82](https://github.com/ribeirosacarlos/ponto-saas-api/commit/aeb1f82d92a702c4f182d3936a2eb1a37b84699d))
* ajuste rotas ([7ad7c96](https://github.com/ribeirosacarlos/ponto-saas-api/commit/7ad7c9623be5dcbc76520e3db3c1ebe34ae69909))
* ajuste url prod swagger ([c15dd42](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c15dd42bd33130d7cdc3a0d8eede35f9ba2a7489))
* ajustes ([8ec3546](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8ec35466cabe0449ba9c04cb6943f8d260fa07e0))
* ajustes doc ([2768077](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2768077991e131425f780329085d44c5200012d9))
* ajustes doc ([9c614e9](https://github.com/ribeirosacarlos/ponto-saas-api/commit/9c614e915b27f3105ffdd9b9a44f570f1705ccf3))
* ajustes dos emails ([1ef1990](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1ef1990fb209517d0e6c7d6ee056a82c286758c3))
* ajustes no fechamento mensal e assinatura da folha de ponto ([4683ecf](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4683ecf06028df67ac26e873abfd17ff27799bf9))
* ajustes para checkout ([b7da528](https://github.com/ribeirosacarlos/ponto-saas-api/commit/b7da528801a4bd01401b9f9a4706175fb0666f25))
* ajustes para checkout ([6c41c77](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6c41c77dc508a474d282b009abf69ef116fc3ddb))
* ajustes para geolocalização ([3edca8b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/3edca8baf4559410a954afeb31bd323da14b21ef))
* alterando upload de document para a S3 ([738edf8](https://github.com/ribeirosacarlos/ponto-saas-api/commit/738edf8e9019df3a57aa0690be6e782d88947438))
* area manager ([f911454](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f9114541718264d3316561a641f1ce1fea962e70))
* cache em coisas do time entryt ([6f91d54](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6f91d54f1d2b8ed5843c3b2e98059a90dbaee372))
* cache em coisas do time entryt ([a27459e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a27459e992638e5abd10f62772747e6791d09009))
* cadastro de company e user ([0030d4d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/0030d4d1555e5b2218cffeecd78db64d14fa6a4f))
* cancelar plano ([b483117](https://github.com/ribeirosacarlos/ponto-saas-api/commit/b48311772b05165f0f1e8ad82127e389f4b283ff))
* colaborador extra (rodar migration) ([38ac859](https://github.com/ribeirosacarlos/ponto-saas-api/commit/38ac8590341fe9cd6f9f7a97fc2279191117865d))
* config logs ([6083f78](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6083f784ca318d473265b2a05ba3ed055e20e40a))
* config logs ([4865a86](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4865a86f6da15407127b39af21adee6f4f1476a2))
* configurando rotas e controllers ([283a219](https://github.com/ribeirosacarlos/ponto-saas-api/commit/283a219821c2383f80210820b46e2ae09d014bdf))
* criando rota para overtime no employe ([580b097](https://github.com/ribeirosacarlos/ponto-saas-api/commit/580b097a8e15e92a13c1d80a7eab19cc31571d1c))
* deploy para aws ([247ba6a](https://github.com/ribeirosacarlos/ponto-saas-api/commit/247ba6acb1103f4078321cb3097a33394f28c637))
* detecta mobile ([343b85e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/343b85e268b53dc4b9fa3bca94f820be80de4c9e))
* device service ([f8afc65](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f8afc65d565832b51a7b7c7e51e2f73369eb3e94))
* device type ([93db2cc](https://github.com/ribeirosacarlos/ponto-saas-api/commit/93db2ccb2b48f0314c308bc44a6cc0cc940ced96))
* docker ([321f779](https://github.com/ribeirosacarlos/ponto-saas-api/commit/321f7798db3ffacd81e9e6d5a8d5083791350f28))
* docker ([23ab551](https://github.com/ribeirosacarlos/ponto-saas-api/commit/23ab551240f4ccacf1b7a6d483d4f6925f57076a))
* documentação swagger ([e122981](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e12298152ab8a57db0bea9e2a2a4852d58266aa9))
* documentos ([de6e02f](https://github.com/ribeirosacarlos/ponto-saas-api/commit/de6e02fa099e63bf117c56ea271772db05491f2f))
* endpoints auditoria ([a677d2e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a677d2e0f7527e323b720076c2622c9911d2d82d))
* endpoints auditoria ([be84cf1](https://github.com/ribeirosacarlos/ponto-saas-api/commit/be84cf18b498a409abb8d00c4c04e6749f913fd8))
* enviando role na rota de employe ([f952fdf](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f952fdfd0c23489737e9db5a309025124ed91c65))
* extra employe ([ce7dc05](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ce7dc0509d349d6ac265f93f8e9408ea19119fb0))
* extra employe ([f11c0df](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f11c0df8e697257b51ad20e2cf9d8379beca6cc7))
* extra employe ([ff5601d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ff5601d35d40fb989100b4a81f5d7bcf0f939d07))
* extra employe ([3c6a16b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/3c6a16b3f4dc31faacd84433979883b75c14ab43))
* ferias ([92c77db](https://github.com/ribeirosacarlos/ponto-saas-api/commit/92c77dba6503587c0e8596721b73cd751e9dbc82))
* ferias ([e3e2b6c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e3e2b6cc9ac8780dd8e44087dbc517fe13f8d0e3))
* fix factory ([5e68a8b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/5e68a8b1b2084103dfbedd9dc0f5c49b490a0698))
* geoloc da empresa ([6df6d68](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6df6d68f0f5cc31f6798abd12bec1feebabdbb62))
* health route ([2302f04](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2302f041fc2e443ee73af6b5d66bc8778aa3e49e))
* hora extra ([f4e4021](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f4e4021aa0d03cebbf12bb4c0690449f703b2169))
* horario por dia ([e60dad8](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e60dad80365fcc01591d66bfd87b144a23e1aa69))
* horas trabalhadas hj ([d3ec20a](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d3ec20a6a58f7e17104c8fd502499531d503baf4))
* horas trabalhadas hj ([463b767](https://github.com/ribeirosacarlos/ponto-saas-api/commit/463b767058e960000f0b0303a1da5a805f185028))
* htacess ([24fa7e7](https://github.com/ribeirosacarlos/ponto-saas-api/commit/24fa7e7d578ab9ffe42604c9d2bc38b87c0b59ae))
* i18n dos blogs ([6cd50bd](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6cd50bd1b0638427fc006c4993984efd2ce5afcf))
* implementa fechamento mensal da folha de ponto ([19261c8](https://github.com/ribeirosacarlos/ponto-saas-api/commit/19261c84a00935ca80e5db8a36a59342bfad4f2f))
* implementa fechamento mensal da folha de ponto ([85eec12](https://github.com/ribeirosacarlos/ponto-saas-api/commit/85eec12ff044f63abe00ff5c63d5a8d6ef615d54))
* implementa módulo de assinatura nativa da folha de ponto ([22ca4bb](https://github.com/ribeirosacarlos/ponto-saas-api/commit/22ca4bbe2771e3ee5278ce9ab052a9354d6e3a42))
* implementação planos e gestão dos planos ([e819ddf](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e819ddf910b7bbf7265221096c562c4c843807ab))
* implementação planos e gestão dos planos ([00e9cb3](https://github.com/ribeirosacarlos/ponto-saas-api/commit/00e9cb3960bb9c0cd8d4cc7f3d50c43a12903e5e))
* implementando comunicados ([41e6a28](https://github.com/ribeirosacarlos/ponto-saas-api/commit/41e6a2868f5c9ff2b35aeba2d146bd0cbf392a52))
* implementando comunicados ([2078e59](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2078e59f540cac22d62a218456f677d3fefe2e1e))
* implementando documentos ([73305e4](https://github.com/ribeirosacarlos/ponto-saas-api/commit/73305e4e27f7fdf1483adf88478fdd9688f852b7))
* implementando feature blog ([f2a67c3](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f2a67c3e11e024baad722b3d885b36fc2f332c27))
* implementando resend envio de email ([97e928d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/97e928d590391bc5d634af632baf254ced43a861))
* implementando rota de reset senha e gera JOB ([1a83444](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1a83444993ffacf762c760a8a6ce153d1594d67f))
* implementando rota de reset senha e gera JOB ([4a7b360](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4a7b36009251d39bc0dff486f342429bb7a52051))
* integracao stripe ([1eff01a](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1eff01aeadbe6a6e947cdea59a311dd3b56bdb27))
* integracao stripe ([a8c443a](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a8c443a21f5a0ee1f341a76250542888b561bef3))
* job de cadastro de admin e empresa ([3e38b37](https://github.com/ribeirosacarlos/ponto-saas-api/commit/3e38b37b8ec27ff14324d95f0ef5ee046fee3596))
* job de cadastro de usuario ([50c3197](https://github.com/ribeirosacarlos/ponto-saas-api/commit/50c31971c2b55fb2bf9f8a47af4c561885f85909))
* jornada de trabalho e holidays ([cbd2764](https://github.com/ribeirosacarlos/ponto-saas-api/commit/cbd2764d8fb3533ac2d3fcfd1adfb22164c343a5))
* jornada de trabalho e holidays ([553aa1d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/553aa1dd15e79059b7b47554af582aecd5debaea))
* liberando cors ([f6db579](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f6db579ee55676748d63ba6d098f879fbaf5c9f4))
* liberando cors ([5833820](https://github.com/ribeirosacarlos/ponto-saas-api/commit/5833820075df205603b82bb9f239f33754970e70))
* listagem de ajustes solicitos ([6962b4e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6962b4e987f7a69169a264612f52fc3b38180ff2))
* listar ajustes do user logado ([a8f8ec5](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a8f8ec5ef680f028bdf92c59f12f590698feea18))
* logs de auditoria ([5e445e2](https://github.com/ribeirosacarlos/ponto-saas-api/commit/5e445e2bf3d7ac22d3a742a76581cab674457cdd))
* logs de auditoria ([4614f25](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4614f254e08242ef2fcfaf22a03c1585d82c7520))
* mais ajustes ([4a425e7](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4a425e750c07d7f6d90c1e9c3c01713925320bfd))
* middlewares e model de role ([10b902d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/10b902d4a0f1affdc908b9d32e50a0482a98cae3))
* migrations iniciais do projeto ([3cfd684](https://github.com/ribeirosacarlos/ponto-saas-api/commit/3cfd684b263d1db288433ca1578d9a1eecaea9f5))
* mudando regra de ponto ([2a53c94](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2a53c94efa0d5aadafd901523ea78add63ed7984))
* mudando regra de ponto ([4d05c7d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4d05c7db11102237ff63018d6e6d574cfbaebc80))
* permitir ponto sem localização ([5a87506](https://github.com/ribeirosacarlos/ponto-saas-api/commit/5a87506430ce603723bd173743bbc7b153f2f48a))
* pgsql ([b2e989a](https://github.com/ribeirosacarlos/ponto-saas-api/commit/b2e989a066031a481b793bb7ca7b6d47544415bc))
* plan seed ([48fa5ab](https://github.com/ribeirosacarlos/ponto-saas-api/commit/48fa5abe22acd76e5d92179e02eecb4558022f51))
* plan seed ([671dba3](https://github.com/ribeirosacarlos/ponto-saas-api/commit/671dba3b2192504debb81e0006cec7d305a827d6))
* plataforma super admin ([7194291](https://github.com/ribeirosacarlos/ponto-saas-api/commit/7194291190e1be7b42b8c84ec6cea21591b8385b))
* plataforma super admin ([8942ab4](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8942ab4bb6e8ab11210b5cbf1f2df0049a579f56))
* policies ([89e484b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/89e484b2a35aa09bd3c523cf7937c0d92f40e33d))
* Politicas Vacaciones 30dias = 2.5 ([90a8d39](https://github.com/ribeirosacarlos/ponto-saas-api/commit/90a8d39a379a24f1b2f0bf41972f09db604c8d70))
* Politicas Vacaciones 30dias = 2.5 ([c7a67f7](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c7a67f764566fe5d188133fe0ff8b2ba2e376128))
* quantidade de employee ([bcfa4d1](https://github.com/ribeirosacarlos/ponto-saas-api/commit/bcfa4d169d48efe67671bb6a5d5af813eb0ca540))
* queue-worker ([a0dec27](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a0dec277dd97a59535ac066302db3fa9904c69c6))
* refatorando rotas ([d794288](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d794288d4e54714ac3746b3b3c07441b4d7884a1))
* regra 1m por ponto ([c0c3b38](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c0c3b383d0509df53c3ac683ec89e18530ba77aa))
* regra de colaborador extra ([e7e27f6](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e7e27f66a694ed99dfc4de2fac92243ae47e3ea8))
* removendo type do time entries ([08cea28](https://github.com/ribeirosacarlos/ponto-saas-api/commit/08cea28feaaa805d9e4f787e8a7917594ddfe43b))
* resend email invite ([860fe70](https://github.com/ribeirosacarlos/ponto-saas-api/commit/860fe70bd4c4f743e1232c13e34f2c0f432c595a))
* resolvendo problema de in out ([211768d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/211768dbd1084b3a8d130bc632fd1002be0f64b6))
* resquest e ajuste no controller ([fd10a67](https://github.com/ribeirosacarlos/ponto-saas-api/commit/fd10a6787812d1fb64afa39bb5c7530ba1943344))
* role super admin ([3c7984f](https://github.com/ribeirosacarlos/ponto-saas-api/commit/3c7984f3b33378b6d9647b86b134f082fe8311d2))
* rota de documentos enviados pelo admin ([48bf207](https://github.com/ribeirosacarlos/ponto-saas-api/commit/48bf207ebea279052d01ad1177b3809f2bfcde5e))
* rota de documentos enviados pelo admin ([de5f658](https://github.com/ribeirosacarlos/ponto-saas-api/commit/de5f65860a309a6e3864e3402edc2645674be1c0))
* rota me ([d5228c1](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d5228c18903e966b54ef5717ea2549b637e8ff31))
* rotas areas ([ad832af](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ad832af5a9f4cc16c7b87c7057e88e7af181d42b))
* seeder pupula pontos ([a4899b5](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a4899b55e18662cc77bbf7e8d07f3f367f8ec5e4))
* seeders de ponto ([f0fef85](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f0fef858c06bc2bf5faf9c7fd70755e44d3ab040))
* seeders de ponto ([8212bec](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8212bec036239459f4a5c22e8f2594b03c70b8e9))
* shift emploey ([99d9b8d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/99d9b8d5923be5df723bfc4f1365cffc2e3d7e70))
* soft delete time entries ([41ba9a9](https://github.com/ribeirosacarlos/ponto-saas-api/commit/41ba9a903ca6f3748577c15fdd31d185b034362e))
* status ponto em aberto ([734e9ba](https://github.com/ribeirosacarlos/ponto-saas-api/commit/734e9ba0959c8158bc97aadf89dfe4074d0b5160))
* status ponto em aberto ([86d5111](https://github.com/ribeirosacarlos/ponto-saas-api/commit/86d5111b5697c8e128b08c4c6e634d636eeb575a))
* subindo rota de settings ([178d5cd](https://github.com/ribeirosacarlos/ponto-saas-api/commit/178d5cd8a8eb715a679cba35f1df12aa8eec1457))
* subindo teste ([3acd1f0](https://github.com/ribeirosacarlos/ponto-saas-api/commit/3acd1f028cca38d9742447a6e66b268183fd9b2c))
* swagger ([e5944a6](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e5944a668ef2714ba5e95563c63bd9bfa60ea8b3))
* swagger ([700c723](https://github.com/ribeirosacarlos/ponto-saas-api/commit/700c723b1f676da4837d962a158b67efec1194f4))
* swagger ([e5efdfd](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e5efdfd8a4fc0d42cbac6b79c991ebce04b490f2))
* teste db ([6c879f0](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6c879f06c64680449239a1894792de1471da3df6))
* timezone padrão por compnay ([7d1af74](https://github.com/ribeirosacarlos/ponto-saas-api/commit/7d1af74c6292c1714ed4b172fd93aeed0a60c949))
* todos as models ([1ed90d2](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1ed90d220f8f27c959bc481ccb254642b4562ffc))
* Traits ([5da49d2](https://github.com/ribeirosacarlos/ponto-saas-api/commit/5da49d2b76c1687988405ec38f2713eee810ee0e))


### Correções de Bugs

* fix:  ([f645f99](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f645f993a12d5bb51aeb05ecbf23b73867fcdbc3))
* fix:  ([c24b1c3](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c24b1c3bfeb66e055d4cbe7239b25ea2a1cabd17))
* fix:  ([9c4c79e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/9c4c79e7482cd0ee5f2a27f7ac75d3c714f53637))
* adc role de plano ativo e permissao ([d8cde39](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d8cde395ef012360e5e4a7ad24e90e4187847da2))
* adc role de plano ativo e permissao ([6a6bb23](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6a6bb2315078e7ee22978a0a7d3b4de0e5ca3851))
* agendamento de férias ([96a3674](https://github.com/ribeirosacarlos/ponto-saas-api/commit/96a367427f7d5f613d66a652c9cf3850075df1e7))
* ajustando bug ([1a9bd76](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1a9bd76900362430c7cf17acc787826c537ff9f2))
* ajustando o codigo para rodar seeders ([12d2ea2](https://github.com/ribeirosacarlos/ponto-saas-api/commit/12d2ea289954101ebefed4da859850bb7380fb2a))
* ajustando relacionamentos do banco de dados ([ae952ad](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ae952ad916477fb58451510824f186f37f169ee6))
* ajustando role ([46903e1](https://github.com/ribeirosacarlos/ponto-saas-api/commit/46903e1fae02e30436410e502eaf56c7d3c4355b))
* ajustando role ([9e20f56](https://github.com/ribeirosacarlos/ponto-saas-api/commit/9e20f56098291d640b8653fd78652221a2c39532))
* ajustando rotas ([528ab98](https://github.com/ribeirosacarlos/ponto-saas-api/commit/528ab987cbabf5c2b77700f9675ee67595128169))
* ajustando solicitação de ajuste de ponto ([850a4ff](https://github.com/ribeirosacarlos/ponto-saas-api/commit/850a4ffdd49b1a7b3bfadfdd7a3eb5b9c99236ca))
* ajuste na logica ([bc97dc1](https://github.com/ribeirosacarlos/ponto-saas-api/commit/bc97dc13f744fef1a228dbfc5dec62334ce58d0e))
* ajuste na logica ([93a1356](https://github.com/ribeirosacarlos/ponto-saas-api/commit/93a1356a82b2d4f3dafe3ef9a189c0fd1d2cbff4))
* ajuste no ajuste ponto ([2401e57](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2401e5770192215e178fec7d0e6f5662a1f2a917))
* ajuste no invate ([535c8c6](https://github.com/ribeirosacarlos/ponto-saas-api/commit/535c8c6ddfa0fe166e2ece4cd26d9a8554870fd8))
* ajuste no invate ([0a6bfbc](https://github.com/ribeirosacarlos/ponto-saas-api/commit/0a6bfbc284bef4e744f6dcb42fc16ad04f8ffc14))
* ajuste para funcionar cors e sanctum ([c60c70f](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c60c70f9f6fe81322449a38c0277ad2396db0fb2))
* ajuste para funcionar cors e sanctum ([0099851](https://github.com/ribeirosacarlos/ponto-saas-api/commit/0099851b89e98070c87a47d9ca6a982356c678e6))
* ajuste quantidade de extra ([c63e590](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c63e59058b0bf3605faf9d438063fb1c2f537446))
* ajuste time entry ([6d29b7c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6d29b7c4e37c93007bc448db03c552d4c99b11dd))
* ajustes ([2880f06](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2880f06598d1590c8b1e43ba64405f3e9d404568))
* ajustes ([70fd724](https://github.com/ribeirosacarlos/ponto-saas-api/commit/70fd724df5c8c6d3474dcef5d74bf34a43a1f43c))
* ajustes de segurança ([b89c287](https://github.com/ribeirosacarlos/ponto-saas-api/commit/b89c287618018a232304cc3635fd121465aa9bb5))
* ajustes deploy ([b889885](https://github.com/ribeirosacarlos/ponto-saas-api/commit/b8898858021ac1210ceed6df1bdeec82f808468a))
* ajustes deploy ([2d8cc14](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2d8cc146e083dc83588c489f6c410a726063c5bc))
* ajustes logicas ([8c99db8](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8c99db89a1a30a22d260cf5a60169b790690fdf4))
* ajustes logicas ([5a8a0b6](https://github.com/ribeirosacarlos/ponto-saas-api/commit/5a8a0b61a593778871bac599e11be7b0d274c9e5))
* ajustes regras ([87ccc48](https://github.com/ribeirosacarlos/ponto-saas-api/commit/87ccc488739c4a49ea825c441b429b4b6fd5935c))
* alterando nome do dominio ([f530d88](https://github.com/ribeirosacarlos/ponto-saas-api/commit/f530d88e71e3147f7053fc8e2457104d0fc5a79e))
* alterando overtime para o model de employee ([e7e86f4](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e7e86f4aaf218e8fbbd8f6ab011c0847f09b647b))
* bug cadastro de employee ([cb4c97d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/cb4c97d8d1571a39e374238a26acb2f72e10620f))
* bug max employe ([2749fbe](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2749fbeed3df6eba73e6e77111d58e288cd26aeb))
* company ([6f92fb0](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6f92fb06f79067bf977d40d7fb610384aebe3f76))
* company ([6ac0af6](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6ac0af6282b34299806f6ca32460e9dcb4c1cb18))
* company ([aa801eb](https://github.com/ribeirosacarlos/ponto-saas-api/commit/aa801ebd11b71b9aac1e555f5dad934c976392cf))
* composer ([ce52b36](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ce52b369a2f099b43411eb359adad93283118d15))
* correct paid break time calculation ([78a6eda](https://github.com/ribeirosacarlos/ponto-saas-api/commit/78a6eda14244c58b46e2ed627f289bf622c0cd42))
* correct paid break time calculation ([236293b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/236293bb9e8d7e099604129baa629f9430b35dc6))
* corrige PDF da folha de ponto assinada ([ef85ed9](https://github.com/ribeirosacarlos/ponto-saas-api/commit/ef85ed910f6f05468088dee84991c4baafa212bb))
* corrigindo erro pdo ([5324d45](https://github.com/ribeirosacarlos/ponto-saas-api/commit/5324d45e7c3f862affbb1f4648274a406573f616))
* corrigindo erro pdo ([7e6b919](https://github.com/ribeirosacarlos/ponto-saas-api/commit/7e6b91931eef4ff96b3283d575b362ef58d34697))
* corrigindo erro swagger generate ([c33ab18](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c33ab1852e24a6a7ea0f70fba60132b4fe0f972a))
* cors ([63165db](https://github.com/ribeirosacarlos/ponto-saas-api/commit/63165db83253ff01d6e23ed73736168e8866fa31))
* data ([2440f90](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2440f90b9aa0fbca53cdeb198619394ebd66d7b7))
* data ([afa6ec1](https://github.com/ribeirosacarlos/ponto-saas-api/commit/afa6ec15a21025365c68cf600fe0c4a9a343c27a))
* Docker ([e76f535](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e76f535771e73aa30da54eb45aef8f22c45aea2a))
* docker-compose atualizado e debug ([46f674c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/46f674c61aeee5ce9642e4d4a9e634dec2564147))
* documents ([a051062](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a051062476876e906d66886726cc7484f24db893))
* employee extra ([2282e28](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2282e28ae6fbcb64139a1c48d07bf6bbb463be8e))
* erro ([0f087e7](https://github.com/ribeirosacarlos/ponto-saas-api/commit/0f087e7d2b84df06059624790df270992ea95f9f))
* erro no company_id ([df566d4](https://github.com/ribeirosacarlos/ponto-saas-api/commit/df566d4ec1bea124333bc142d474429a6928a9fa))
* erros de segurança ([9573d70](https://github.com/ribeirosacarlos/ponto-saas-api/commit/9573d70546b0240e8df35e8fb096413ca2b44c8e))
* exception ([e15827c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e15827c6157f60a897769164334319daff2928aa))
* filesystem ([c7ce173](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c7ce17371c646da3612ce9785610f9a3aa217931))
* filesystem ([abb2fd5](https://github.com/ribeirosacarlos/ponto-saas-api/commit/abb2fd5ee2d925dd795cce58e72fc5643b7155f7))
* holidays e overtime ([64e8ddf](https://github.com/ribeirosacarlos/ponto-saas-api/commit/64e8ddf1e8bb33b19202b798cb2ef46b2f56bff7))
* holidays e overtime ([6cb0f40](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6cb0f40378aa96b98440a5d73d72ceeec68a0586))
* hora extra ([e42d58c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/e42d58c5f60d677e1a0bfe9d58082132f0bcb389))
* hora extra ([3c3629c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/3c3629c85b935e279235b6405255c0660dca91ac))
* hora extra ([25a48fa](https://github.com/ribeirosacarlos/ponto-saas-api/commit/25a48faed7ad9831d9a02dd55e5c3ffec966118d))
* hora extra ([40f6c47](https://github.com/ribeirosacarlos/ponto-saas-api/commit/40f6c471dd5d09e67c2650cc34a4a7a1bf93396a))
* in ou in ([7864561](https://github.com/ribeirosacarlos/ponto-saas-api/commit/7864561027650e076294a455b85c55542fab54a1))
* liberando cors para o  front ([9e82407](https://github.com/ribeirosacarlos/ponto-saas-api/commit/9e824078f09fe1044959792bc424340ab3a147d5))
* listagem time entry ([6115899](https://github.com/ribeirosacarlos/ponto-saas-api/commit/61158994a374ff059632b062a20ada236d5d4c3a))
* log no cloud watch ([27d4e84](https://github.com/ribeirosacarlos/ponto-saas-api/commit/27d4e846764211f9390fa71dae9d783e8582ee86))
* log teste ([6509401](https://github.com/ribeirosacarlos/ponto-saas-api/commit/6509401cac2be9429832315f754acd28e5d74987))
* logs ([83ce5d8](https://github.com/ribeirosacarlos/ponto-saas-api/commit/83ce5d8f75b84b6baff49b4b85f723ea10209dc1))
* logs ([d4bae23](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d4bae2367e506f3eac201b686b494ec4d5e7903c))
* mais ajutestes ([2ef44d1](https://github.com/ribeirosacarlos/ponto-saas-api/commit/2ef44d182f8bc2ba9d87ce198b7ea4e449fe2334))
* mudando url ([876bdaa](https://github.com/ribeirosacarlos/ponto-saas-api/commit/876bdaae755219f3cfdac57c85852820a202d1d3))
* open status ([8b40a82](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8b40a82243d2ee06118017303d34c8e64c4d7fa3))
* overtime ([aa1736c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/aa1736c4ad8d643e5677e94f13b591ffd3f97a0b))
* overtime por periodo ([bcd89ad](https://github.com/ribeirosacarlos/ponto-saas-api/commit/bcd89ad06b5fa2ff2e859b3a403fe967fd668c67))
* overtime por periodo ([d16302d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d16302dd063448421562b1cd3ac509eaf18701f8))
* paginação de users ([c2cdb6b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c2cdb6b2c489ef8695e1b1fd0adf0cd4d6f64a97))
* pagination ([25f620e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/25f620e65d7abcf062163e7a3dde702d5934a0b2))
* per_page entries ([96438e5](https://github.com/ribeirosacarlos/ponto-saas-api/commit/96438e5af17b5de686fd9422c563ad7b69a92b2b))
* permissao policy ([0dd2d95](https://github.com/ribeirosacarlos/ponto-saas-api/commit/0dd2d95feac2b0216fb46c61a1c3db464f53adb3))
* remove ultimo migration ([bb1ae93](https://github.com/ribeirosacarlos/ponto-saas-api/commit/bb1ae93b61474fabb09648a260b75f84b16c2b2b))
* remove ultimo migration ([4de77fa](https://github.com/ribeirosacarlos/ponto-saas-api/commit/4de77fae676be695f77a8700423c2170ef1280d1))
* removendo timeenyt ([8dac62c](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8dac62c696d2767856bbd676df9e0994f78c2768))
* role employe ([efa238b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/efa238b1583eef9bec4d14ad8831d9cf785ec348))
* role employe ([a6aef5b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a6aef5bebd65bdceaed6b9314dd570fc79eaf55c))
* role employe ([c9c6e21](https://github.com/ribeirosacarlos/ponto-saas-api/commit/c9c6e21174176879b1ea1c92aa85a12088ec942e))
* roles usuario ([1e1bb4b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1e1bb4b22696d8bdf15b2b88e1f9577542bd2adb))
* route health ([27a8cfc](https://github.com/ribeirosacarlos/ponto-saas-api/commit/27a8cfc217c1c0f088d81e4284d39bedabd0ac2a))
* security ([9362f20](https://github.com/ribeirosacarlos/ponto-saas-api/commit/9362f2092b88aecf6afaefbfa993b2e84fbdfff7))
* seeder compnay ([0e2742d](https://github.com/ribeirosacarlos/ponto-saas-api/commit/0e2742d518c13ef70b0c136f6983736937b7d434))
* seeder compnay ([424acf9](https://github.com/ribeirosacarlos/ponto-saas-api/commit/424acf919fe50ae668631f62e13eb045247bd185))
* seeders ([a870c35](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a870c351219eedecaedfd25f61a972beeddab96e))
* swagger ([07ed1c3](https://github.com/ribeirosacarlos/ponto-saas-api/commit/07ed1c3cfd1faf87028e4c937f5b58aaf92574fc))
* teste ([1c75dca](https://github.com/ribeirosacarlos/ponto-saas-api/commit/1c75dca365fc95b3fdf222f04f3b29a884a953e6))
* time entry ([a0466ed](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a0466edc274cf35177b7f402c5a7164fd82d7dc1))
* timenetry ([d3b53cf](https://github.com/ribeirosacarlos/ponto-saas-api/commit/d3b53cfffd7b1ffa9aadb7277f6d069416748900))
* tratando role do superadmin ([8578a3b](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8578a3bbb6846c55006212a5d5542c47adeb5e30))
* tratando role do superadmin ([55c94af](https://github.com/ribeirosacarlos/ponto-saas-api/commit/55c94af06169582cafa98e04ec8b342181baeb28))
* trocando permissao ([fe4f70e](https://github.com/ribeirosacarlos/ponto-saas-api/commit/fe4f70e0da5ac0bac08a919ce84dcff73d8c2e1c))
* ultimo commit ([8f588d6](https://github.com/ribeirosacarlos/ponto-saas-api/commit/8f588d6e584b318e6ef310edf002fcf69ea554d0))
* ultimo commit ([a272f1f](https://github.com/ribeirosacarlos/ponto-saas-api/commit/a272f1f8bd6cbd2a8f42d260a9893c51efcb0aed))
* work today ([126640a](https://github.com/ribeirosacarlos/ponto-saas-api/commit/126640a63fc35ba9ef38305e83e1c28306fd8269))
* worked ([497cb23](https://github.com/ribeirosacarlos/ponto-saas-api/commit/497cb2366bf7772844b51f438179be251ddbf4f3))
