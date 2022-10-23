export type ISettingValue = string | boolean;

export interface ISetting {
  value: any;
  label: string;
  values: Record<string, ISettingValue>;
}

export type ISettings = Record<string, ISetting>;

export type IPluginStrings = Record<string, string>;

export type IMenuItems = Record<
  string,
  {
    title: string;
    subtitle?: string;
    submenu?: Record<string, string>;
  }
>;

export interface IGitPackageBranch {
  name: string;
  url: string;
  zip: string;
  default: boolean;
}

export interface IGitPackageRaw {
  key: string;
  name: string;
  private: boolean;
  provider: string;
  branches: Record<string, IGitPackageBranch>;
  baseUrl: string;
  apiUrl: string;
}

export interface IGitPackage extends IGitPackageRaw {
  deployKey: string;
  theme: boolean;
  saveAsMustUsePlugin: boolean;
  version: string;
  dir: string;
  log: Array<{
    ref: 'push-to-deploy' | 'update-trigger';
    time: number;
    prevVersion: string;
    newVersion: string;
  }>;
}

export type IGitPackages = Array<IGitPackage>;

export interface IGitWordPressPackage {
  type: 'theme' | 'plugin';
  name: string;
}
