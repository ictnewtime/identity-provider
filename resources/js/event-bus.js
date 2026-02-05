import mitt from 'mitt';

const emitter = mitt();

export const EventBus = {
    $on: (...args) => emitter.on(...args),
    $off: (...args) => emitter.off(...args),
    $emit: (...args) => emitter.emit(...args)
};