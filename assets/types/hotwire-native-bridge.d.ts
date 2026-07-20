declare module '@hotwired/hotwire-native-bridge' {
  import { Controller } from '@hotwired/stimulus';

  export class BridgeComponent extends Controller {
    static component: string;
    static shouldLoad: boolean;

    get component(): string;
    get enabled(): boolean;

    send(
      event: string,
      data?: Record<string, unknown>,
      callback?: (message: unknown) => void,
    ): void;
  }
}
