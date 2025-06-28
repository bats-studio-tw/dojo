export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string;
}

// getUserInfo API 返回的用户信息类型
export interface UserInfo {
  uid: string;
  saasId: string;
  twitterName: string | null;
  discordName: string | null;
  invitePoint: number | null;
  dividePoint: number | null;
  available: number;
  pfp: string | null;
  vip: string | null;
  isVip: boolean | null;
  rankValue: number;
  rankPercent: string;
  expValue: number | null;
  ticketAvailable: number | null;
  ticketUsed: number | null;
  voucherAvailable: number | null;
  extraBoostValue: number | null;
  boostValue: number | null;
  ojoValue: number;
  credentialsAvailable: number;
  OSPVerified: boolean;
  XVerified: boolean;
  XVerifiedType: string | null;
  ospAvatar: string | null;
  experienceVoucherAvailable: number | null;
  seasonPointAvailable: number | null;
}

// getUserInfo API 完整响应类型
export interface GetUserInfoResponse {
  success: boolean;
  code: string;
  msgKey: string;
  obj: UserInfo;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth?: {
    user?: User;
  };
};
